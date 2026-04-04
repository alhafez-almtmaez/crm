<?php

namespace App\Services\Admin;

use App\Models\Device;
use Illuminate\Support\Facades\Http;

class WhatsAppGroupService
{
    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function options(): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp_api.url', ''), '/');
        if ($baseUrl === '') {
            return [];
        }

        $device = Device::query()
            ->where('status', 'CONNECTED')
            ->whereNotNull('session_id')
            ->where('session_id', '!=', '')
            ->first();

        if (! $device) {
            return [];
        }

        $response = Http::withHeader('x-api-key', (string) config('services.whatsapp_api.key', config('app.key')))
            ->timeout(20)
            ->get("{$baseUrl}/client/getChats/{$device->session_id}");

        if ($response->failed()) {
            return [];
        }

        $chats = $response->json('chats');
        if (! is_array($chats)) {
            return [];
        }

        $options = [];

        foreach ($chats as $chat) {
            if (! is_array($chat)) {
                continue;
            }

            $server = (string) data_get($chat, 'id.server', '');
            if ($server !== 'g.us') {
                continue;
            }

            $value = (string) data_get($chat, 'id._serialized', '');
            $label = (string) data_get($chat, 'name', '');

            if ($value === '' || $label === '') {
                continue;
            }

            $options[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }
}
