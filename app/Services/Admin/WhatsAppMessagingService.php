<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Exceptions\WhatsAppRecipientNotRegisteredException;
use App\Models\Device;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppMessagingService
{
    private const DEFAULT_MEDIA_URL = 'https://dash.alhafez-almtmaez.com/media/logos/logo.png';

    /** @var array<string, bool> */
    private array $registrationResults = [];

    public function __construct(private readonly ?WhatsAppSessionService $sessions = null) {}

    /**
     * @param  array<int, string>  $phones
     */
    public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
    {
        $recipients = $this->recipientChatIds($phones, $groupSerialized);

        // Business actions update their records only after this method succeeds.
        // Queuing a failed attempt here could later send a message describing an
        // action that never happened, so pending delivery must always be explicit
        // and tied to a verifiable source.
        $this->sendMediaCaptionToChatIds($recipients, $content, queueOnFailure: false);
    }

    public function sendTextMessage(string $phone, string $content, ?Device $preferredDevice = null): void
    {
        $this->sendMediaCaptionToChatIds(
            $this->recipientChatIds([$phone]),
            $content,
            preferredDevice: $preferredDevice,
        );
    }

    /**
     * @param  array<int, string>  $chatIds
     */
    public function sendMediaCaptionToChatIds(
        array $chatIds,
        string $content,
        ?string $mediaUrl = null,
        bool $queueOnFailure = false,
        ?string $sourceType = null,
        int|string|null $sourceId = null,
        ?Device $preferredDevice = null,
    ): void {
        $baseUrl = rtrim((string) config('services.whatsapp_api.url', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException(__('whatsapp.api_not_configured'));
        }

        $recipients = $this->normalizeChatIds($chatIds);
        if ($recipients === []) {
            throw new RuntimeException(__('students.no_recipients_provided'));
        }

        $device = $this->sendingDevice($preferredDevice);

        if (! $device) {
            $message = __('students.whatsapp_device_not_connected');
            $this->queuePendingMessage(
                $recipients,
                $content,
                $mediaUrl,
                $message,
                $queueOnFailure,
                $sourceType,
                $sourceId,
            );

            throw new WhatsAppMessageSendException($message, $recipients);
        }

        $device = $this->assertPersonalRecipientsAreRegistered(
            $device,
            $recipients,
            $baseUrl,
        );

        foreach ($recipients as $index => $chatId) {
            $sessionId = (string) $device->session_id;
            $response = $this->apiRequest()->post(
                "{$baseUrl}/client/sendMessage/{$sessionId}",
                $this->messagePayload($chatId, $content, $mediaUrl),
            );

            if ($this->isRejectedResponse($response) && $this->isSessionUnavailableResponse($response)) {
                $replacementDevice = $this->sessionService()->connectedDevice($device);

                if ($replacementDevice) {
                    $device = $replacementDevice;
                    $response = $this->apiRequest()->post(
                        "{$baseUrl}/client/sendMessage/{$device->session_id}",
                        $this->messagePayload($chatId, $content, $mediaUrl),
                    );
                }
            }

            if (! $this->messageWasSent($response)) {
                $message = $this->responseErrorMessage($response, __('whatsapp.send_failed'));
                $unsentRecipients = array_slice($recipients, $index);
                $this->queuePendingMessage(
                    $unsentRecipients,
                    $content,
                    $mediaUrl,
                    $message,
                    $queueOnFailure,
                    $sourceType,
                    $sourceId,
                );

                throw new WhatsAppMessageSendException($message, $unsentRecipients);
            }

            if ($index < count($recipients) - 1) {
                $this->sleepBetweenMessages();
            }
        }
    }

    private function apiRequest(): PendingRequest
    {
        return Http::withHeader('x-api-key', (string) config('services.whatsapp_api.key', config('app.key')))
            ->timeout(20);
    }

    private function isSessionUnavailableResponse(Response $response): bool
    {
        return in_array(
            $response->json('message') ?? $response->json('error'),
            ['session_not_found', 'session_not_connected'],
            true,
        );
    }

    private function sessionService(): WhatsAppSessionService
    {
        return $this->sessions ?? app(WhatsAppSessionService::class);
    }

    private function sendingDevice(?Device $preferredDevice): ?Device
    {
        if ($preferredDevice !== null
            && is_string($preferredDevice->session_id)
            && trim($preferredDevice->session_id) !== '') {
            return $preferredDevice;
        }

        return Device::query()
            ->where('status', 'CONNECTED')
            ->whereNotNull('session_id')
            ->where('session_id', '!=', '')
            ->first();
    }

    /**
     * Verify every personal recipient before the first message is sent. A failed
     * or ambiguous verification stops the whole batch, so callers never mistake
     * an unchecked number for a delivered message.
     *
     * @param  array<int, string>  $recipients
     */
    private function assertPersonalRecipientsAreRegistered(
        Device $device,
        array $recipients,
        string $baseUrl,
    ): Device {
        $unregisteredNumbers = [];

        foreach ($recipients as $chatId) {
            $number = $this->personalNumberFromChatId($chatId);
            if ($number === null) {
                continue;
            }

            $sessionId = (string) $device->session_id;
            $cacheKey = "{$sessionId}:{$number}";
            $registered = $this->registrationResults[$cacheKey] ?? null;

            if (! is_bool($registered)) {
                $response = $this->registrationResponse(
                    $baseUrl,
                    $sessionId,
                    $number,
                    $recipients,
                );

                if ($this->isRejectedResponse($response) && $this->isSessionUnavailableResponse($response)) {
                    try {
                        $replacementDevice = $this->sessionService()->connectedDevice($device);
                    } catch (Throwable) {
                        throw new WhatsAppMessageSendException(
                            __('whatsapp.registration_check_failed'),
                            $recipients,
                        );
                    }

                    if ($replacementDevice) {
                        $device = $replacementDevice;
                        $sessionId = (string) $device->session_id;
                        $cacheKey = "{$sessionId}:{$number}";
                        $registered = $this->registrationResults[$cacheKey] ?? null;

                        if (! is_bool($registered)) {
                            $response = $this->registrationResponse(
                                $baseUrl,
                                $sessionId,
                                $number,
                                $recipients,
                            );
                        }
                    }
                }

                if (! is_bool($registered)) {
                    if (! $response->successful()
                        || $response->json('success') !== true
                        || ! is_bool($response->json('result'))) {
                        throw new WhatsAppMessageSendException(
                            $this->responseErrorMessage($response, __('whatsapp.registration_check_failed')),
                            $recipients,
                        );
                    }

                    $registered = $response->json('result');
                    $this->registrationResults[$cacheKey] = $registered;
                }
            }

            if (! $registered) {
                $unregisteredNumbers[] = $number;
            }
        }

        if ($unregisteredNumbers !== []) {
            throw new WhatsAppRecipientNotRegisteredException(
                __('whatsapp.numbers_not_registered', [
                    'numbers' => implode(', ', array_values(array_unique($unregisteredNumbers))),
                ]),
                $recipients,
            );
        }

        return $device;
    }

    /**
     * @param  array<int, string>  $recipients
     */
    private function registrationResponse(
        string $baseUrl,
        string $sessionId,
        string $number,
        array $recipients,
    ): Response {
        try {
            return $this->apiRequest()->post(
                "{$baseUrl}/client/isRegisteredUser/{$sessionId}",
                ['number' => $number],
            );
        } catch (Throwable) {
            // Registration verification happens before the first send attempt,
            // so this is a known zero-delivery failure and remains retryable.
            throw new WhatsAppMessageSendException(
                __('whatsapp.registration_check_failed'),
                $recipients,
            );
        }
    }

    private function personalNumberFromChatId(string $chatId): ?string
    {
        if (! preg_match('/^(\d+)@(s\.whatsapp\.net|c\.us)$/', trim($chatId), $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function isRejectedResponse(Response $response): bool
    {
        return ! $response->successful() || $response->json('success') !== true;
    }

    private function messageWasSent(Response $response): bool
    {
        $message = $response->json('message');

        return $response->successful()
            && $response->json('success') === true
            && is_array($message)
            && $message !== [];
    }

    private function responseErrorMessage(Response $response, string $fallback): string
    {
        foreach (['message', 'error'] as $key) {
            $message = $response->json($key);
            if (is_string($message) && trim($message) !== '') {
                return $message;
            }
        }

        return $fallback;
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($normalized, '00')) {
            $normalized = substr($normalized, 2);
        }

        if (str_starts_with($normalized, '0') && strlen($normalized) === 10) {
            return '962'.substr($normalized, 1);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(string $chatId, string $content, ?string $mediaUrl = null): array
    {
        $mediaUrl = is_string($mediaUrl) ? trim($mediaUrl) : '';

        if ($mediaUrl === '') {
            return [
                'chatId' => $chatId,
                'contentType' => 'string',
                'content' => $content,
            ];
        }

        return [
            'chatId' => $chatId,
            'contentType' => 'MessageMediaFromURL',
            'content' => $mediaUrl,
            'options' => ['caption' => $content],
        ];
    }

    /**
     * @param  array<int, string>  $phones
     * @return array<int, string>
     */
    public function recipientChatIds(array $phones, ?string $groupSerialized = null): array
    {
        $recipients = [];
        foreach ($phones as $phone) {
            if (! is_string($phone)) {
                continue;
            }

            $normalized = $this->normalizePhone($phone);
            if ($normalized === '') {
                continue;
            }

            $recipients[] = "{$normalized}@s.whatsapp.net";
        }

        if ($groupSerialized !== null && trim($groupSerialized) !== '') {
            $recipients[] = trim($groupSerialized);
        }

        return $this->normalizeChatIds($recipients);
    }

    /**
     * Describe which intended recipients were reached before a batch send failed.
     *
     * @param  array<int, string>  $phones
     * @return array{partial_delivery: bool, delivered_chat_ids: array<int, string>, remaining_chat_ids: array<int, string>, group_serialized: string|null}
     */
    public function deliveryFailureMeta(
        array $phones,
        ?string $groupSerialized,
        WhatsAppMessageSendException $exception,
    ): array {
        $intendedChatIds = $this->recipientChatIds($phones, $groupSerialized);
        $unsentLookup = array_fill_keys($this->normalizeChatIds($exception->unsentChatIds()), true);
        $deliveredChatIds = [];
        $remainingChatIds = [];

        foreach ($intendedChatIds as $chatId) {
            if (isset($unsentLookup[$chatId])) {
                $remainingChatIds[] = $chatId;
            } else {
                $deliveredChatIds[] = $chatId;
            }
        }

        return [
            'partial_delivery' => $deliveredChatIds !== [] && $remainingChatIds !== [],
            'delivered_chat_ids' => $deliveredChatIds,
            'remaining_chat_ids' => $remainingChatIds,
            'group_serialized' => $groupSerialized,
        ];
    }

    /**
     * @param  array<int, string>  $chatIds
     * @return array<int, string>
     */
    private function normalizeChatIds(array $chatIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $chatId): string => trim((string) $chatId),
            $chatIds,
        ))));
    }

    /**
     * @param  array<int, string>  $chatIds
     */
    private function queuePendingMessage(
        array $chatIds,
        string $content,
        ?string $mediaUrl,
        string $lastError,
        bool $queueOnFailure,
        ?string $sourceType,
        int|string|null $sourceId,
    ): void {
        if (! $queueOnFailure) {
            return;
        }

        try {
            app(WhatsAppPendingMessageService::class)->enqueue(
                $chatIds,
                $content,
                $mediaUrl,
                sourceType: $sourceType,
                sourceId: $sourceId,
                lastError: $lastError,
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to queue pending WhatsApp message.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sleepBetweenMessages(): void
    {
        $seconds = max(0, (int) config('services.whatsapp_api.message_delay_seconds', 30));

        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
