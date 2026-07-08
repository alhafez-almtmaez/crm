<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\Device;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppMessagingService
{
    private const DEFAULT_MEDIA_URL = 'https://dash.alhafez-almtmaez.com/media/logos/logo.png';

    /**
     * @param  array<int, string>  $phones
     */
    public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
    {
        $recipients = $this->recipientsFromPhones($phones, $groupSerialized);

        $this->sendMediaCaptionToChatIds($recipients, $content, self::DEFAULT_MEDIA_URL);
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

        $mediaUrl ??= self::DEFAULT_MEDIA_URL;

        foreach ($recipients as $index => $chatId) {
            $response = $this->apiRequest()->post("{$baseUrl}/client/sendMessage/{$device->session_id}", [
                'chatId' => $chatId,
                'contentType' => 'MessageMediaFromURL',
                'content' => $mediaUrl,
                'options' => ['caption' => $content],
            ]);

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

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
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
