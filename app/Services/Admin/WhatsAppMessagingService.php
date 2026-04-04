<?php

namespace App\Services\Admin;

use App\Models\Device;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppMessagingService
{
    /**
     * @param array<int, string> $phones
     */
    public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
    {
        $baseUrl = rtrim((string) config('services.whatsapp_api.url', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException(__('whatsapp.api_not_configured'));
        }

        $device = Device::query()
            ->where('status', 'CONNECTED')
            ->whereNotNull('session_id')
            ->where('session_id', '!=', '')
            ->first();

        if (!$device) {
            throw new RuntimeException(__('students.whatsapp_device_not_connected'));
        }

        $recipients = [];
        foreach ($phones as $phone) {
            $normalized = $this->normalizePhone($phone);
            if ($normalized === '') {
                continue;
            }

            $recipients[] = "{$normalized}@s.whatsapp.net";
        }

        $recipients = array_values(array_unique($recipients));

        if ($groupSerialized !== null && trim($groupSerialized) !== '') {
            $recipients[] = trim($groupSerialized);
        }

        if ($recipients === []) {
            throw new RuntimeException(__('students.no_recipients_provided'));
        }

        foreach ($recipients as $chatId) {
            $response = $this->apiRequest()->post("{$baseUrl}/client/sendMessage/{$device->session_id}", [
                'chatId' => $chatId,
                'contentType' => 'MessageMediaFromURL',
                'content' => 'https://dash.alhafez-almtmaez.com/media/logos/logo.png',
                'options' => ['caption' => $content],
            ]);

            if ($response->failed()) {
                $message = (string) ($response->json('message') ?? $response->json('error') ?? __('whatsapp.send_failed'));
                throw new RuntimeException($message);
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
}
