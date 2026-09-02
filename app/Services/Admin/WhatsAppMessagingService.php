<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Exceptions\WhatsAppRecipientNotRegisteredException;
use App\Models\Device;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class WhatsAppMessagingService
{
    private const DEFAULT_MEDIA_URL = 'https://dash.alhafez-almtmaez.com/media/logos/logo.png';

    private const PDF_SIGNATURE = '%PDF-';

    /** Keeps the base64 JSON request below wwebjs-api's default body limit. */
    private const MAX_PDF_BYTES = 8_000_000;

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

    /**
     * Send one PDF document with a caption to personal WhatsApp numbers.
     *
     * @param  array<int, string>  $phones
     */
    public function sendPdfDocument(
        array $phones,
        string $caption,
        string $pdfBytes,
        string $filename,
    ): void {
        $filename = $this->validatedPdfFilename($filename);
        $this->assertValidPdf($pdfBytes);

        $encodedPdf = base64_encode($pdfBytes);
        $filesize = strlen($pdfBytes);

        $this->sendPreparedMessageToChatIds(
            $this->recipientChatIds($phones),
            $caption,
            static fn (string $chatId): array => [
                'chatId' => $chatId,
                'contentType' => 'MessageMedia',
                'content' => [
                    'mimetype' => 'application/pdf',
                    'data' => $encodedPdf,
                    'filename' => $filename,
                    'filesize' => $filesize,
                ],
                'options' => [
                    'caption' => $caption,
                    'sendMediaAsDocument' => true,
                ],
            ],
            requestTimeoutSeconds: 60,
            acceptSuccessfulEnvelopeWithoutMessage: true,
        );
    }

    /**
     * Fail before expensive message preparation when no intended recipient is eligible.
     * Successful personal-number checks remain cached for the subsequent send.
     *
     * @param  array<int, string>  $phones
     */
    public function assertHasEligibleRecipients(array $phones, ?string $groupSerialized = null): void
    {
        $baseUrl = rtrim((string) config('services.whatsapp_api.url', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException(__('whatsapp.api_not_configured'));
        }

        $recipients = $this->recipientChatIds($phones, $groupSerialized);
        if ($recipients === []) {
            throw new RuntimeException(__('students.no_recipients_provided'));
        }

        $device = $this->sendingDevice(null);
        if (! $device) {
            throw new WhatsAppMessageSendException(
                __('students.whatsapp_device_not_connected'),
                $recipients,
            );
        }

        $this->filterRegisteredRecipients($device, $recipients, $baseUrl);
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
        $this->sendPreparedMessageToChatIds(
            $chatIds,
            $content,
            fn (string $chatId): array => $this->messagePayload($chatId, $content, $mediaUrl),
            $mediaUrl,
            $queueOnFailure,
            $sourceType,
            $sourceId,
            $preferredDevice,
        );
    }

    /**
     * @param  array<int, string>  $chatIds
     * @param  callable(string): array<string, mixed>  $payloadForChatId
     */
    private function sendPreparedMessageToChatIds(
        array $chatIds,
        string $content,
        callable $payloadForChatId,
        ?string $mediaUrl = null,
        bool $queueOnFailure = false,
        ?string $sourceType = null,
        int|string|null $sourceId = null,
        ?Device $preferredDevice = null,
        int $requestTimeoutSeconds = 20,
        bool $acceptSuccessfulEnvelopeWithoutMessage = false,
    ): void {
        $baseUrl = rtrim((string) config('services.whatsapp_api.url', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException(__('whatsapp.api_not_configured'));
        }

        $intendedRecipients = $this->normalizeChatIds($chatIds);
        if ($intendedRecipients === []) {
            throw new RuntimeException(__('students.no_recipients_provided'));
        }

        $device = $this->sendingDevice($preferredDevice);

        if (! $device) {
            $message = __('students.whatsapp_device_not_connected');
            $this->queuePendingMessage(
                $intendedRecipients,
                $content,
                $mediaUrl,
                $message,
                $queueOnFailure,
                $sourceType,
                $sourceId,
            );

            throw new WhatsAppMessageSendException($message, $intendedRecipients);
        }

        $verification = $this->filterRegisteredRecipients(
            $device,
            $intendedRecipients,
            $baseUrl,
        );
        $device = $verification['device'];
        $recipients = $verification['recipients'];
        $skippedRecipients = $verification['skipped_recipients'];

        foreach ($recipients as $index => $chatId) {
            $sessionId = (string) $device->session_id;
            $response = $this->apiRequest($requestTimeoutSeconds)->post(
                "{$baseUrl}/client/sendMessage/{$sessionId}",
                $payloadForChatId($chatId),
            );

            if ($this->isRejectedResponse($response) && $this->isSessionUnavailableResponse($response)) {
                $replacementDevice = $this->sessionService()->connectedDevice($device);

                if ($replacementDevice) {
                    $device = $replacementDevice;
                    $response = $this->apiRequest($requestTimeoutSeconds)->post(
                        "{$baseUrl}/client/sendMessage/{$device->session_id}",
                        $payloadForChatId($chatId),
                    );
                }
            }

            if (! $this->messageWasSent($response, $acceptSuccessfulEnvelopeWithoutMessage)) {
                $message = $this->responseErrorMessage($response, __('whatsapp.send_failed'));
                $remainingLookup = array_fill_keys([
                    ...$skippedRecipients,
                    ...array_slice($recipients, $index),
                ], true);
                $unsentRecipients = array_values(array_filter(
                    $intendedRecipients,
                    static fn (string $recipient): bool => isset($remainingLookup[$recipient]),
                ));
                $this->queuePendingMessage(
                    $unsentRecipients,
                    $content,
                    $mediaUrl,
                    $message,
                    $queueOnFailure,
                    $sourceType,
                    $sourceId,
                );

                throw new WhatsAppMessageSendException(
                    $message,
                    $unsentRecipients,
                    deliveryAttempted: true,
                );
            }

            if ($index < count($recipients) - 1) {
                $this->waitBetweenMessages();
            }
        }
    }

    private function apiRequest(int $timeoutSeconds = 20): PendingRequest
    {
        return Http::withHeader('x-api-key', (string) config('services.whatsapp_api.key', config('app.key')))
            ->timeout($timeoutSeconds);
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
     * Verify every personal recipient before the first message is sent. Numbers
     * known not to be registered are skipped while valid numbers and groups
     * continue normally. An ambiguous verification still stops the whole batch.
     *
     * @param  array<int, string>  $recipients
     * @return array{device: Device, recipients: array<int, string>, skipped_recipients: array<int, string>}
     */
    private function filterRegisteredRecipients(
        Device $device,
        array $recipients,
        string $baseUrl,
    ): array {
        $eligibleRecipients = [];
        $skippedRecipients = [];
        $unregisteredNumbers = [];

        foreach ($recipients as $chatId) {
            $number = $this->personalNumberFromChatId($chatId);
            if ($number === null) {
                $eligibleRecipients[] = $chatId;

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

            if ($registered) {
                $eligibleRecipients[] = $chatId;
            } else {
                $skippedRecipients[] = $chatId;
                $unregisteredNumbers[] = $number;
            }
        }

        if ($eligibleRecipients === [] && $unregisteredNumbers !== []) {
            throw new WhatsAppRecipientNotRegisteredException(
                __('whatsapp.numbers_not_registered', [
                    'numbers' => implode(', ', array_values(array_unique($unregisteredNumbers))),
                ]),
                $recipients,
            );
        }

        return [
            'device' => $device,
            'recipients' => $eligibleRecipients,
            'skipped_recipients' => $skippedRecipients,
        ];
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

    private function messageWasSent(
        Response $response,
        bool $acceptSuccessfulEnvelopeWithoutMessage = false,
    ): bool {
        if (! $response->successful() || $response->json('success') !== true) {
            return false;
        }

        if ($acceptSuccessfulEnvelopeWithoutMessage) {
            return true;
        }

        $message = $response->json('message');

        return is_array($message) && $message !== [];
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

    private function assertValidPdf(string $pdfBytes): void
    {
        if (! str_starts_with($pdfBytes, self::PDF_SIGNATURE)) {
            throw new InvalidArgumentException('The supplied media is not a valid PDF document.');
        }

        if (strlen($pdfBytes) > self::MAX_PDF_BYTES) {
            throw new InvalidArgumentException('The PDF document is too large to send through WhatsApp.');
        }
    }

    private function validatedPdfFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === ''
            || strlen($filename) > 255
            || ! str_ends_with(strtolower($filename), '.pdf')
            || preg_match('~[\x00-\x1F\x7F/\\\\]~', $filename) === 1) {
            throw new InvalidArgumentException('The PDF filename is invalid.');
        }

        return $filename;
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

    /**
     * Wait for the configured pacing interval and return the chosen seconds.
     * When no maximum is configured, existing fixed-delay behavior is preserved.
     */
    public function waitBetweenMessages(): int
    {
        $minimum = max(0, (int) config('services.whatsapp_api.message_delay_seconds', 30));
        $configuredMaximum = config('services.whatsapp_api.message_delay_max_seconds');
        $maximum = $configuredMaximum === null || $configuredMaximum === ''
            ? $minimum
            : max($minimum, (int) $configuredMaximum);
        $seconds = $minimum === $maximum
            ? $minimum
            : random_int($minimum, $maximum);

        if ($seconds > 0) {
            sleep($seconds);
        }

        return $seconds;
    }
}
