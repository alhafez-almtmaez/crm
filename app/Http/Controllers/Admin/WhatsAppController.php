<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WhatsappSendRequest;
use App\Models\Device;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function index(): Response
    {
        $device = $this->firstOrCreateDevice();
        $apiConfigured = $this->hasApiBaseUrl();
        $qr = $apiConfigured ? $this->resolveQrAndSyncStatus($device) : null;

        return Inertia::render('Admin/WhatsApp', [
            'device' => $device->only(['id', 'name', 'status', 'session_id']),
            'qr' => $qr,
            'apiConfigured' => $apiConfigured,
        ]);
    }

    public function replayScan(Device $device): JsonResponse
    {
        if (! $this->hasApiBaseUrl()) {
            return response()->json([
                'message' => __('whatsapp.api_not_configured'),
            ], 422);
        }

        $qr = $this->resolveQrAndSyncStatus($device);

        return response()->json([
            'qr' => $qr,
            'connected' => $qr === null && $device->status === 'CONNECTED',
            'status' => $device->status,
        ]);
    }

    public function send(WhatsappSendRequest $request, Device $device): JsonResponse
    {
        if (! $this->hasApiBaseUrl()) {
            return response()->json([
                'message' => __('whatsapp.api_not_configured'),
            ], 422);
        }

        if ($device->session_id === null || $device->session_id === '') {
            $device->update([
                'session_id' => $this->generateSessionId($device->name ?? 'Device'),
            ]);
        }

        $response = $this->apiRequest()->post($this->apiBaseUrl()."/client/sendMessage/{$device->session_id}", [
            'chatId' => $request->validated('phone').'@s.whatsapp.net',
            'contentType' => 'string',
            'content' => $request->validated('message'),
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => $this->responseErrorMessage($response, __('whatsapp.send_failed')),
            ], 422);
        }

        return response()->json([
            'message' => __('whatsapp.send_success'),
        ]);
    }

    public function destroy(Device $device): JsonResponse
    {
        if ($this->hasApiBaseUrl() && $device->session_id) {
            $this->apiRequest()->get($this->apiBaseUrl()."/session/terminate/{$device->session_id}");
        }

        $device->delete();

        return response()->json([
            'message' => __('whatsapp.device_deleted_successfully'),
        ]);
    }

    private function firstOrCreateDevice(): Device
    {
        $device = Device::query()->first();

        if ($device) {
            return $device;
        }

        return Device::query()->create([
            'name' => 'Main Device',
            'status' => 'PENDING',
            'session_id' => $this->generateSessionId('Main Device'),
        ]);
    }

    private function resolveQrAndSyncStatus(Device $device): ?string
    {
        $statusResponse = $this->apiRequest()
            ->get($this->apiBaseUrl()."/session/status/{$device->session_id}");

        if (strtoupper((string) $statusResponse->json('state')) === 'CONNECTED') {
            if ($device->status !== 'CONNECTED') {
                $device->update(['status' => 'CONNECTED']);
            }

            return null;
        }

        $qr = $this->sessionAdd($device);

        if ($device->status !== 'PENDING') {
            $device->update(['status' => 'PENDING']);
        }

        return $qr;
    }

    private function sessionAdd(Device $device, int $attempt = 0): ?string
    {
        if ($attempt >= 3) {
            return null;
        }

        $response = $this->apiRequest()
            ->get($this->apiBaseUrl()."/session/qr/{$device->session_id}/image");

        if ($this->isQrImageResponse($response)) {
            return 'data:image/png;base64,'.base64_encode($response->body());
        }

        $message = $this->responseErrorMessage($response);

        if ($message !== 'session_not_found') {
            $this->apiRequest()->get($this->apiBaseUrl()."/session/terminate/{$device->session_id}");
        }

        $newSessionId = $this->generateSessionId($device->name ?? 'Device');
        $device->update([
            'session_id' => $newSessionId,
            'status' => 'PENDING',
        ]);

        $this->apiRequest()->get($this->apiBaseUrl()."/session/start/{$newSessionId}");
        sleep(2);
        $device->refresh();

        return $this->sessionAdd($device, $attempt + 1);
    }

    private function isQrImageResponse(HttpClientResponse $response): bool
    {
        $contentType = strtolower((string) $response->header('Content-Type'));

        if (str_contains($contentType, 'image/')) {
            return true;
        }

        if (! $response->ok()) {
            return false;
        }

        return json_decode($response->body(), true) === null;
    }

    private function responseErrorMessage(HttpClientResponse $response, ?string $fallback = null): string
    {
        $json = $response->json();
        if (is_array($json)) {
            $message = $json['message'] ?? $json['error'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return $fallback ?? __('whatsapp.request_failed');
    }

    private function apiRequest(): PendingRequest
    {
        return Http::withHeader('x-api-key', $this->apiKey())
            ->timeout(20);
    }

    private function hasApiBaseUrl(): bool
    {
        return $this->apiBaseUrl() !== '';
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('services.whatsapp_api.url', ''), '/');
    }

    private function apiKey(): string
    {
        return (string) config('services.whatsapp_api.key', config('app.key'));
    }

    private function generateSessionId(string $name): string
    {
        return Str::slug($name.' '.random_int(10, 1000), '_');
    }
}
