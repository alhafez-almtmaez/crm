<?php

use App\Exceptions\WhatsAppMessageSendException;
use App\Exceptions\WhatsAppRecipientNotRegisteredException;
use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Device;
use App\Models\Evaluation;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppPendingMessage;
use App\Services\Admin\WhatsAppMessagingService;
use App\Services\Admin\WhatsAppPendingMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function successfulWwebjsSendPayload(string $id = 'message-1'): array
{
    return [
        'success' => true,
        'message' => [
            'id' => ['_serialized' => $id],
        ],
    ];
}

test('business messages are not queued automatically when no device is connected', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['+962 79 000 0111'],
        'Absence alert',
        '120363000000000000@g.us',
    ))->toThrow(RuntimeException::class);

    expect(WhatsAppPendingMessage::query()->count())->toBe(0);
});

test('pending whatsapp messages are sent when a connected device is available', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['962790000111@s.whatsapp.net'],
        'content' => 'Queued absence alert',
        'source_type' => WhatsAppPendingMessage::SOURCE_DIRECT,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::response(successfulWwebjsSendPayload()),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 1,
        'failed' => 0,
        'stale' => 0,
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

test('pending messages with an unregistered recipient become stale instead of retrying forever', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['962790000111@s.whatsapp.net'],
        'content' => 'Queued direct message',
        'source_type' => WhatsAppPendingMessage::SOURCE_DIRECT,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 0,
        'failed' => 0,
        'stale' => 1,
    ])
        ->and($pending->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_STALE)
        ->and($pending->last_error)->toBe(
            'stale: recipient_not_registered: '.__('whatsapp.numbers_not_registered', [
                'numbers' => '962790000111',
            ]),
        );

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('pending messages remain retryable when recipient registration cannot be verified', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['962790000111@s.whatsapp.net'],
        'content' => 'Queued direct message',
        'source_type' => WhatsAppPendingMessage::SOURCE_DIRECT,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => false,
            'error' => 'temporary upstream failure',
        ], 500),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 0,
        'failed' => 1,
        'stale' => 0,
    ])
        ->and($pending->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_PENDING)
        ->and($pending->last_error)->toBe('temporary upstream failure')
        ->and($pending->available_at?->isFuture())->toBeTrue();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('jordanian local phone numbers are normalized before whatsapp send', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::response(successfulWwebjsSendPayload()),
    ]);

    app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['079 000 0111'],
        'Direct phone message',
    );

    expect(Http::recorded()
        ->map(fn (array $pair): string => $pair[0]->url())
        ->all())->toBe([
            'https://wa.test/client/isRegisteredUser/main_session',
            'https://wa.test/client/sendMessage/main_session',
        ]);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/isRegisteredUser/main_session'
        && $request['number'] === '962790000111');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '962790000111@s.whatsapp.net'
        && $request['contentType'] === 'string'
        && $request['content'] === 'Direct phone message');
});

test('unregistered personal recipients stop the whole batch before any message is sent', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    $exception = null;

    try {
        app(WhatsAppMessagingService::class)->sendMediaCaption(
            ['079 000 0111'],
            'Absence alert',
            '120363000000000000@g.us',
        );
    } catch (WhatsAppRecipientNotRegisteredException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(WhatsAppRecipientNotRegisteredException::class)
        ->and($exception?->unsentChatIds())->toBe([
            '962790000111@s.whatsapp.net',
            '120363000000000000@g.us',
        ]);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('a mixed batch is fully checked and sends nothing when one personal number is unregistered', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::sequence()
            ->push(['success' => true, 'result' => true])
            ->push(['success' => true, 'result' => false]),
    ]);

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['0790000111', '0790000222'],
        'Absence alert',
        '120363000000000000@g.us',
    ))->toThrow(WhatsAppRecipientNotRegisteredException::class, '962790000222');

    expect(Http::recorded()
        ->map(fn (array $pair): mixed => $pair[0]['number'])
        ->all())->toBe(['962790000111', '962790000222']);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('group only messages do not call the personal registration endpoint', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/sendMessage/main_session' => Http::response(successfulWwebjsSendPayload()),
    ]);

    app(WhatsAppMessagingService::class)->sendMediaCaption(
        [],
        'Group announcement',
        '120363000000000000@g.us',
    );

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/isRegisteredUser/'));
    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '120363000000000000@g.us');
});

test('ambiguous registration responses fail closed before sending', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => false,
            'result' => true,
        ]),
    ]);

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['0790000111'],
        'Direct message',
    ))->toThrow(WhatsAppMessageSendException::class, __('whatsapp.registration_check_failed'));

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('a registration connection failure is known to occur before any delivery', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::failedConnection('connection refused'),
    ]);

    $exception = null;

    try {
        app(WhatsAppMessagingService::class)->sendMediaCaption(
            ['0790000111'],
            'Direct message',
            '120363000000000000@g.us',
        );
    } catch (WhatsAppMessageSendException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(WhatsAppMessageSendException::class)
        ->and($exception?->getMessage())->toBe(__('whatsapp.registration_check_failed'))
        ->and($exception?->unsentChatIds())->toBe([
            '962790000111@s.whatsapp.net',
            '120363000000000000@g.us',
        ]);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('a successful http status with a rejected send body is not treated as delivered', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::response([
            'success' => false,
            'error' => 'send rejected',
        ]),
    ]);

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['0790000111'],
        'Direct message',
    ))->toThrow(WhatsAppMessageSendException::class, 'send rejected');
});

test('a send response without a message object is not treated as delivered', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::response(['success' => true]),
    ]);

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['0790000111'],
        'Direct message',
    ))->toThrow(WhatsAppMessageSendException::class, __('whatsapp.send_failed'));
});

test('the admin test sender uses the same registration preflight', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $device = Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    $this->actingAs($admin, 'web')
        ->postJson(route('admin.whatsapp.send', $device), [
            'phone' => '0790000111',
            'message' => 'Test message',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('whatsapp.numbers_not_registered', [
            'numbers' => '962790000111',
        ]));

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('sending retries with the connected api session when the local session is stale', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    $device = Device::factory()->connected()->create(['session_id' => 'stale_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/stale_session' => Http::response([
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
        'https://wa.test/client/isRegisteredUser/live_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/live_session' => Http::response(successfulWwebjsSendPayload()),
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

test('registration is retried once when the same session reconnects', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::sequence()
            ->push([
                'success' => false,
                'error' => 'session_not_connected',
            ], 404)
            ->push(['success' => true, 'result' => true]),
        'https://wa.test/session/status/main_session' => Http::response([
            'success' => true,
            'state' => 'CONNECTED',
        ]),
        'https://wa.test/client/sendMessage/main_session' => Http::response(successfulWwebjsSendPayload()),
    ]);

    app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['079 000 0111'],
        'Recovered on same session',
    );

    Http::assertSentCount(4);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '962790000111@s.whatsapp.net'
        && $request['content'] === 'Recovered on same session');
});

test('sending is retried once when the same session reconnects after registration', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => true,
        ]),
        'https://wa.test/client/sendMessage/main_session' => Http::sequence()
            ->push([
                'success' => false,
                'error' => 'session_not_connected',
            ], 404)
            ->push(successfulWwebjsSendPayload()),
        'https://wa.test/session/status/main_session' => Http::response([
            'success' => true,
            'state' => 'CONNECTED',
        ]),
    ]);

    app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['079 000 0111'],
        'Recovered send on same session',
    );

    Http::assertSentCount(4);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '962790000111@s.whatsapp.net'
        && $request['content'] === 'Recovered send on same session');
});

test('failed business send does not store unsent recipients as pending', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::sequence()
            ->push(successfulWwebjsSendPayload('message-1'))
            ->push(['message' => 'device disconnected'], 500),
    ]);

    expect(fn () => app(WhatsAppMessagingService::class)->sendMediaCaption(
        ['+962 79 000 0111', '+962 79 000 0222'],
        'Absence alert',
    ))->toThrow(RuntimeException::class, 'device disconnected');

    expect(WhatsAppPendingMessage::query()->count())->toBe(0);
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
        'source_type' => WhatsAppPendingMessage::SOURCE_DIRECT,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::sequence()
            ->push(successfulWwebjsSendPayload('message-1'))
            ->push(['message' => 'device disconnected'], 500),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 0,
        'failed' => 1,
        'stale' => 0,
    ]);

    $pending->refresh();

    expect($pending->status)->toBe(WhatsAppPendingMessage::STATUS_PENDING)
        ->and($pending->attempts)->toBe(1)
        ->and($pending->chat_ids)->toBe(['962790000222@s.whatsapp.net'])
        ->and($pending->last_error)->toBe('device disconnected');
});

test('legacy pending messages without a verifiable source are quarantined instead of sent', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['120363000000000000@g.us'],
        'content' => 'Legacy message with an old destination',
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake();

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 0,
        'failed' => 0,
        'stale' => 1,
    ]);

    expect($pending->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_STALE)
        ->and($pending->last_error)->toBe('stale: unverifiable_source');

    Http::assertNothingSent();
});

test('absence pending messages resolve current contacts and the evaluation group before sending', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'group_serialized' => '120363999999999999@g.us',
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'parent_phone_number' => '0791111222',
        'phone_number' => null,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);
    $rule = AbsenceRule::factory()->sendMessageAction()->create([
        'deduction_points_count' => 0,
    ]);
    $log = AbsenceRuleExecutionLog::factory()->failed()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_student_id' => null,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'absence_rule_id' => $rule->id,
        'action' => AbsenceRule::ACTION_SEND_MESSAGE,
        'recipient_phones' => ['962790000000'],
        'message_content' => 'Verified absence message',
        'sent_to_group' => true,
        'deduction_points_count' => 0,
        'meta' => ['error' => 'device disconnected'],
    ]);
    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['120363000000000000@g.us'],
        'content' => 'Untrusted old content',
        'source_type' => WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG,
        'source_id' => $log->id,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::response(successfulWwebjsSendPayload()),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 1,
        'failed' => 0,
        'stale' => 0,
    ]);

    expect($pending->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_SENT)
        ->and($pending->chat_ids)->toBe([
            '962791111222@s.whatsapp.net',
            '120363999999999999@g.us',
        ])
        ->and($pending->content)->toBe('Verified absence message');

    expect($log->refresh()->was_message_sent)->toBeTrue()
        ->and($log->recipient_phones)->toBe(['0791111222'])
        ->and($log->meta['error'])->toBeNull()
        ->and($log->meta['pending_message_id'])->toBe($pending->id)
        ->and($log->meta['group_serialized'])->toBe('120363999999999999@g.us');

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '962791111222@s.whatsapp.net'
        && $request['content'] === 'Verified absence message');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request['chatId'] === '120363999999999999@g.us'
        && $request['content'] === 'Verified absence message');

    $duplicate = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['120363999999999999@g.us'],
        'content' => 'Verified absence message',
        'source_type' => WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG,
        'source_id' => $log->id,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    expect(app(WhatsAppPendingMessageService::class)->flushPending()['stale'])->toBe(1)
        ->and($duplicate->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_STALE)
        ->and($duplicate->last_error)->toBe('stale: source_already_sent');

    Http::assertSentCount(3);
});

test('pending absence message is quarantined when its business action was not applied', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);
    $rule = AbsenceRule::factory()->freezeAction()->create([
        'deduction_points_count' => 0,
    ]);
    $log = AbsenceRuleExecutionLog::factory()->failed()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_student_id' => null,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'absence_rule_id' => $rule->id,
        'action' => AbsenceRule::ACTION_FREEZE_STUDENT,
        'message_content' => 'You were frozen',
        'student_was_frozen' => false,
        'student_freeze_id' => null,
        'deduction_points_count' => 0,
        'meta' => ['error' => 'device disconnected'],
    ]);
    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['962790000000@s.whatsapp.net'],
        'content' => 'You were frozen',
        'source_type' => WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG,
        'source_id' => $log->id,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake();

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary['stale'])->toBe(1)
        ->and($pending->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_STALE)
        ->and($pending->last_error)->toBe('stale: business_action_not_applied');

    Http::assertNothingSent();
});

test('partially delivered absence pending message is stopped instead of duplicating recipients', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);
    Device::factory()->connected()->create(['session_id' => 'main_session']);

    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'group_serialized' => '120363999999999999@g.us',
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'parent_phone_number' => '0791111222',
        'phone_number' => null,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);
    $rule = AbsenceRule::factory()->sendMessageAction()->create([
        'deduction_points_count' => 0,
    ]);
    $log = AbsenceRuleExecutionLog::factory()->failed()->create([
        'evaluation_id' => $evaluation->id,
        'evaluation_student_id' => null,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'absence_rule_id' => $rule->id,
        'action' => AbsenceRule::ACTION_SEND_MESSAGE,
        'message_content' => 'Verified absence message',
        'sent_to_group' => true,
        'deduction_points_count' => 0,
        'meta' => ['error' => 'device disconnected'],
    ]);
    $pending = WhatsAppPendingMessage::query()->create([
        'chat_ids' => ['962790000000@s.whatsapp.net'],
        'content' => 'Old pending snapshot',
        'source_type' => WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG,
        'source_id' => $log->id,
        'status' => WhatsAppPendingMessage::STATUS_PENDING,
        'available_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response(['success' => true, 'result' => true]),
        'https://wa.test/client/sendMessage/main_session' => Http::sequence()
            ->push(successfulWwebjsSendPayload('message-1'))
            ->push(['message' => 'group send failed'], 500),
    ]);

    $summary = app(WhatsAppPendingMessageService::class)->flushPending();

    expect($summary)->toBe([
        'checked' => 1,
        'sent' => 0,
        'failed' => 0,
        'stale' => 1,
    ])
        ->and($pending->refresh()->status)->toBe(WhatsAppPendingMessage::STATUS_STALE)
        ->and($pending->chat_ids)->toBe(['120363999999999999@g.us'])
        ->and($pending->last_error)->toBe('stale: partial_delivery_requires_review')
        ->and($log->refresh()->was_message_sent)->toBeFalse()
        ->and($log->meta['pending_partial_delivery'])->toBeTrue()
        ->and($log->meta['pending_delivered_chat_ids'])->toBe(['962791111222@s.whatsapp.net'])
        ->and($log->meta['pending_remaining_chat_ids'])->toBe(['120363999999999999@g.us'])
        ->and(app(WhatsAppPendingMessageService::class)->retirePendingForSource(
            WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG,
            $log->id,
        ))->toBeFalse();

    expect(app(WhatsAppPendingMessageService::class)->flushPending()['checked'])->toBe(0);
    Http::assertSentCount(3);
});
