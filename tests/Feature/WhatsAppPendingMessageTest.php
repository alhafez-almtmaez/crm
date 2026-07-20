<?php

use App\Models\Device;
use App\Models\WhatsAppPendingMessage;
use App\Services\Admin\WhatsAppMessagingService;
use App\Services\Admin\WhatsAppPendingMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('whatsapp message is stored as pending when no device is connected', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['+962 79 000 0111'],
        'Absence alert',
        '120363000000000000@g.us',
    ))->toThrow(RuntimeException::class);

    $message = WhatsAppPendingMessage::query()->firstOrFail();

    expect($message->status)->toBe(WhatsAppPendingMessage::STATUS_PENDING)
        ->and($message->chat_ids)->toBe([
            '962790000111@s.whatsapp.net',
            '120363000000000000@g.us',
        ])
        ->and($message->content)->toBe('Absence alert')
        ->and($message->last_error)->toBe(__('students.whatsapp_device_not_connected'));
});

test('pending whatsapp messages are sent when a connected device is available', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['962790000111@s.whatsapp.net'],
        'content' => 'Queued absence alert',
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/sendMessage/main_session' => Http::response(['ok' => true]),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 1,
        'failed' => 0,
    ]);

    $pending->refresh();

    expect($pending->status)->toBe(WhatsAppPendingMessage::STATUS_SENT)
        ->and($pending->sent_at)->not->toBeNull()
        ->and($pending->attempts)->toBe(1);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '962790000111@s.whatsapp.net'
        && $request['contentType'] === 'string'
        && $request['content'] === 'Queued absence alert');
});

test('jordanian local phone numbers are normalized before whatsapp send', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/sendMessage/main_session' => Http::response(['ok' => true]),
    ]);

    app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['079 000 0111'],
        'Direct phone message',
    );

    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '962790000111@s.whatsapp.net'
        && $request['contentType'] === 'string'
        && $request['content'] === 'Direct phone message');
});

test('sending retries with the connected api session when the local session is stale', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    $device = Device::factory()->connected()->create(['session_id' => 'stale_session']);

    Http::fake([
        'https://wa.test/client/sendMessage/stale_session' => Http::response([
            'success' => false,
            'error' => 'session_not_found',
        ], 404),
        'https://wa.test/session/status/stale_session' => Http::response([
            'success' => false,
            'state' => null,
            'message' => 'session_not_found',
        ]),
        'https://wa.test/session/getSessions' => Http::response([
            'success' => true,
            'result' => ['live_session'],
        ]),
        'https://wa.test/session/status/live_session' => Http::response([
            'success' => true,
            'state' => 'CONNECTED',
        ]),
        'https://wa.test/client/sendMessage/live_session' => Http::response(['success' => true]),
    ]);

    app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['079 000 0111'],
        'Recovered message',
    );

    expect($device->refresh()->session_id)->toBe('live_session')
        ->and(WhatsAppPendingMessage::query()->count())->toBe(0);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/live_session'
        && $request['chatId'] === '962790000111@s.whatsapp.net'
        && $request['content'] === 'Recovered message');
});

test('failed whatsapp send stores only unsent recipients as pending', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/sendMessage/main_session' => Http::sequence()
            ->push(['ok' => true])
            ->push(['message' => 'device disconnected'], 500),
    ]);

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['+962 79 000 0111', '+962 79 000 0222'],
        'Absence alert',
    ))->toThrow(RuntimeException::class, 'device disconnected');

    $message = WhatsAppPendingMessage::query()->firstOrFail();

    expect($message->status)->toBe(WhatsAppPendingMessage::STATUS_PENDING)
        ->and($message->chat_ids)->toBe(['962790000222@s.whatsapp.net'])
        ->and($message->last_error)->toBe('device disconnected');
});

test('pending retry keeps only unsent recipients when sending stops mid batch', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => [
            '962790000111@s.whatsapp.net',
            '962790000222@s.whatsapp.net',
        ],
        'content' => 'Queued absence alert',
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/sendMessage/main_session' => Http::sequence()
            ->push(['ok' => true])
            ->push(['message' => 'device disconnected'], 500),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 0,
        'failed' => 1,
    ]);

    $pending->refresh();

    expect($pending->status)->toBe(WhatsAppPendingMessage::STATUS_PENDING)
        ->and($pending->attempts)->toBe(1)
        ->and($pending->chat_ids)->toBe(['962790000222@s.whatsapp.net'])
        ->and($pending->last_error)->toBe('device disconnected');
});
