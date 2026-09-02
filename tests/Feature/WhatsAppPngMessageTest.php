<?php

use App\Exceptions\WhatsAppRecipientNotRegisteredException;
use App\Models\Device;
use App\Services\Admin\WhatsAppMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function successfulPngWwebjsPayload(): array
{
    return [
        'success' => true,
        'message' => [
            'id' => ['_serialized' => 'png-message-1'],
        ],
    ];
}

test('whatsapp png message keeps registration verification and sends message media as base64', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.key', 'whatsapp-key');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => true,
        ]),
        'https://wa.test/client/sendMessage/main_session' => Http::response(successfulPngWwebjsPayload()),
    ]);

    $png = "\x89PNG\r\n\x1a\ncertificate-image";
    $filename = 'certificate-HMT-2026-ABCDEFGH.png';

    $messaging = app(WhatsAppMessagingService::class);
    $messaging->assertHasEligibleRecipients(['079 000 0111']);
    $messaging->sendPngCaption(
        ['079 000 0111'],
        'شهادة الإنجاز',
        $png,
        $filename,
    );

    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/isRegisteredUser/main_session'
        && $request['number'] === '962790000111');

    expect(Http::recorded(fn ($request): bool => str_contains(
        $request->url(),
        '/client/isRegisteredUser/',
    )))->toHaveCount(1);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://wa.test/client/sendMessage/main_session'
        && $request->hasHeader('x-api-key', 'whatsapp-key')
        && $request['chatId'] === '962790000111@s.whatsapp.net'
        && $request['contentType'] === 'MessageMedia'
        && $request['content']['mimetype'] === 'image/png'
        && $request['content']['data'] === base64_encode($png)
        && $request['content']['filename'] === $filename
        && $request['content']['filesize'] === strlen($png)
        && $request['options']['caption'] === 'شهادة الإنجاز');
});

test('whatsapp png message does not send media when the personal number is not registered', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    $png = "\x89PNG\r\n\x1a\ncertificate-image";

    expect(fn () => app(WhatsAppMessagingService::class)->assertHasEligibleRecipients(
        ['079 000 0111'],
    ))->toThrow(WhatsAppRecipientNotRegisteredException::class);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('whatsapp png message rejects invalid bytes before contacting the api', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Http::fake();

    expect(fn () => app(WhatsAppMessagingService::class)->sendPngCaption(
        ['079 000 0111'],
        'شهادة الإنجاز',
        'not-a-png',
        'certificate-HMT-2026-ABCDEFGH.png',
    ))->toThrow(InvalidArgumentException::class, 'not a valid PNG');

    Http::assertNothingSent();
});
