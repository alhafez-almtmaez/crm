<?php

namespace App\Services\Admin;

use App\Models\Device;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class WhatsAppSessionService
{
    public function connectedDevice(?Device $preferredDevice = null): ?Device
    {
        if ($this->apiBaseUrl() === '') {
            return null;
        }

        $devices = $this->candidateDevices($preferredDevice);

        foreach ($devices as $device) {
            if ($this->sessionIsConnected((string) $device->session_id)) {
                return $this->markConnected($device);
            }
        }

        $connectedSessionIds = $this->connectedApiSessionIds();
        if ($connectedSessionIds === []) {
            return null;
        }

        $knownDevice = Device::query()
            ->whereIn('session_id', $connectedSessionIds)
            ->first();

        if ($knownDevice) {
            return $this->markConnected($knownDevice);
        }

        if (count($connectedSessionIds) !== 1) {
            return null;
        }

        $device = $preferredDevice ?? $devices->first();
        if (! $device) {
            return null;
        }

        $device->update([
            'session_id' => $connectedSessionIds[0],
            'status' => 'CONNECTED',
        ]);

        return $device->refresh();
    }

    /**
     * @return Collection<int, Device>
     */
    private function candidateDevices(?Device $preferredDevice): Collection
    {
        $devices = Device::query()
            ->whereNotNull('session_id')
            ->where('session_id', '!=', '')
            ->orderByRaw("CASE WHEN status = 'CONNECTED' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        if (! $preferredDevice) {
            return $devices;
        }

        return $devices
            ->reject(fn (Device $device): bool => $device->is($preferredDevice))
            ->prepend($preferredDevice);
    }

    /**
     * @return array<int, string>
     */
    private function connectedApiSessionIds(): array
    {
        $response = $this->apiRequest()
            ->get($this->apiBaseUrl().'/session/getSessions');

        if ($response->failed() || $response->json('success') !== true) {
            return [];
        }

        $sessionIds = $response->json('result');
        if (! is_array($sessionIds)) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $sessionId): string => trim((string) $sessionId),
            $sessionIds,
        )), fn (string $sessionId): bool => $sessionId !== '' && $this->sessionIsConnected($sessionId)));
    }

    private function sessionIsConnected(string $sessionId): bool
    {
        if ($sessionId === '') {
            return false;
        }

        $response = $this->apiRequest()
            ->get($this->apiBaseUrl()."/session/status/{$sessionId}");

        return $response->successful()
            && $response->json('success') === true
            && strtoupper((string) $response->json('state')) === 'CONNECTED';
    }

    private function markConnected(Device $device): Device
    {
        if ($device->status !== 'CONNECTED') {
            $device->update(['status' => 'CONNECTED']);
        }

        return $device;
    }

    private function apiRequest(): PendingRequest
    {
        return Http::withHeader('x-api-key', (string) config('services.whatsapp_api.key', config('app.key')))
            ->timeout(20);
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('services.whatsapp_api.url', ''), '/');
    }
}
