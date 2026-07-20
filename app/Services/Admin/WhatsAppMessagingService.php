<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
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

    public function __construct(private readonly ?WhatsAppSessionService $sessions = null) {}

    /**
     * @param  array<int, string>  $phones
     */
    public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
    {
        $recipients = $this->recipientsFromPhones($phones, $groupSerialized);

        $this->sendMediaCaptionToChatIds($recipients, $content);
    }

    /**
     * @param  array<int, string>  $chatIds
     */
    public function sendMediaCaptionToChatIds(
        array $chatIds,
        string $content,
        ?string $mediaUrl = null,
        bool $queueOnFailure = true,
    ): void {
        $baseUrl = rtrim((string) config('services.whatsapp_api.url', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException(__('whatsapp.api_not_configured'));
        }

        $recipients = $this->normalizeChatIds($chatIds);
        if ($recipients === []) {
            throw new RuntimeException(__('students.no_recipients_provided'));
        }

        $device = Device::query()
            ->where('status', 'CONNECTED')
            ->whereNotNull('session_id')
            ->where('session_id', '!=', '')
            ->first();

        if (! $device) {
            $message = __('students.whatsapp_device_not_connected');
            $this->queuePendingMessage($recipients, $content, $mediaUrl, $message, $queueOnFailure);

            throw new WhatsAppMessageSendException($message, $recipients);
        }

        foreach ($recipients as $index => $chatId) {
            $sessionId = (string) $device->session_id;
            $response = $this->apiRequest()->post(
                "{$baseUrl}/client/sendMessage/{$sessionId}",
                $this->messagePayload($chatId, $content, $mediaUrl),
            );

            if ($response->failed() && $this->isSessionNotFoundResponse($response)) {
                $replacementDevice = $this->sessionService()->connectedDevice($device);

                if ($replacementDevice && $replacementDevice->session_id !== $sessionId) {
                    $device = $replacementDevice;
                    $response = $this->apiRequest()->post(
                        "{$baseUrl}/client/sendMessage/{$device->session_id}",
                        $this->messagePayload($chatId, $content, $mediaUrl),
                    );
                }
            }

            if ($response->failed()) {
                $message = (string) ($response->json('message') ?? $response->json('error') ?? __('whatsapp.send_failed'));
                $unsentRecipients = array_slice($recipients, $index);
                $this->queuePendingMessage($unsentRecipients, $content, $mediaUrl, $message, $queueOnFailure);

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

    private function isSessionNotFoundResponse(Response $response): bool
    {
        return ($response->json('message') ?? $response->json('error')) === 'session_not_found';
    }

    private function sessionService(): WhatsAppSessionService
    {
        return $this->sessions ?? app(WhatsAppSessionService::class);
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
    private function recipientsFromPhones(array $phones, ?string $groupSerialized = null): array
    {
        $recipients = [];
        foreach ($phones as $phone) {
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
    ): void {
        if (! $queueOnFailure) {
            return;
        }

        try {
            app(WhatsAppPendingMessageService::class)->enqueue(
                $chatIds,
                $content,
                $mediaUrl,
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
