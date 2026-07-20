<?php

namespace App\Services\Admin;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsAppGroupService
{
    public function __construct(private readonly WhatsAppSessionService $sessions) {}

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function options(): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp_api.url', ''), '/');
        if ($baseUrl === '') {
            return [];
        }

        $device = $this->sessions->connectedDevice();

        if (! $device) {
            return [];
        }

        $response = $this->apiRequest()
            ->get("{$baseUrl}/client/getContacts/{$device->session_id}");

        $records = $this->responseRecords($response, 'contacts');

        if ($records === null) {
            $response = $this->apiRequest()
                ->get("{$baseUrl}/client/getChats/{$device->session_id}");

            $records = $this->responseRecords($response, 'chats');
        }

        if ($records === null) {
            return [];
        }

        $options = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $value = (string) data_get($record, 'id._serialized', '');
            $server = (string) data_get($record, 'id.server', '');

            if ($server !== 'g.us' && ! str_ends_with($value, '@g.us')) {
                continue;
            }

            $label = trim((string) (
                data_get($record, 'name')
                ?? data_get($record, 'pushname')
                ?? data_get($record, 'shortName')
                ?? ''
            ));

            if ($value === '') {
                continue;
            }

            $options[$value] = [
                'label' => $label !== '' ? $label : $value,
                'value' => $value,
            ];
        }

        $options = array_values($options);
        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function responseRecords(Response $response, string $key): ?array
    {
        if ($response->failed() || $response->json('success') !== true) {
            return null;
        }

        $records = $response->json($key);

        return is_array($records) ? $records : null;
    }

    private function apiRequest(): PendingRequest
    {
        return Http::withHeader('x-api-key', (string) config('services.whatsapp_api.key', config('app.key')))
            ->timeout(30);
    }
}
