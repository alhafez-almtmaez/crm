<?php

use App\Models\Device;
use App\Services\Admin\WhatsAppGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.key', 'test-key');
});

test('whatsapp groups are loaded from contacts without loading every chat', function () {
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/session/status/main_session' => Http::response([
            'success' => true,
            'state' => 'CONNECTED',
        ]),
        'https://wa.test/client/getContacts/main_session' => Http::response([
            'success' => true,
            'contacts' => [
                [
                    'id' => ['server' => 'c.us', '_serialized' => '962790000111@c.us'],
                    'name' => 'Direct contact',
                ],
                [
                    'id' => ['server' => 'g.us', '_serialized' => '120363000000000002@g.us'],
                    'name' => 'Second group',
                ],
                [
                    'id' => ['server' => '', '_serialized' => '120363000000000001@g.us'],
                    'name' => 'First group',
                ],
            ],
        ]),
    ]);

    expect(app(WhatsAppGroupService::class)->options())->toBe([
        ['label' => 'First group', 'value' => '120363000000000001@g.us'],
        ['label' => 'Second group', 'value' => '120363000000000002@g.us'],
    ]);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/getChats/'));
});

test('a stale local session is replaced by the only connected api session', function () {
    $device = Device::factory()->connected()->create(['session_id' => 'stale_session']);

    Http::fake([
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
        'https://wa.test/client/getContacts/live_session' => Http::response([
            'success' => true,
            'contacts' => [[
                'id' => ['server' => 'g.us', '_serialized' => '120363000000000001@g.us'],
                'name' => 'Recovered group',
            ]],
        ]),
    ]);

    expect(app(WhatsAppGroupService::class)->options())->toBe([
        ['label' => 'Recovered group', 'value' => '120363000000000001@g.us'],
    ]);

    expect($device->refresh()->session_id)->toBe('live_session')
        ->and($device->status)->toBe('CONNECTED');
});

test('chat loading remains available as a fallback for older api installations', function () {
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/session/status/main_session' => Http::response([
            'success' => true,
            'state' => 'CONNECTED',
        ]),
        'https://wa.test/client/getContacts/main_session' => Http::response([
            'success' => false,
            'error' => 'not_supported',
        ], 404),
        'https://wa.test/client/getChats/main_session' => Http::response([
            'success' => true,
            'chats' => [[
                'id' => ['server' => 'g.us', '_serialized' => '120363000000000001@g.us'],
                'name' => 'Fallback group',
            ]],
        ]),
    ]);

    expect(app(WhatsAppGroupService::class)->options())->toBe([
        ['label' => 'Fallback group', 'value' => '120363000000000001@g.us'],
    ]);
});
