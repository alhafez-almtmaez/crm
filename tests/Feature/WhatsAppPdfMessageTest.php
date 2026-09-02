<?php

use App\Exceptions\WhatsAppRecipientNotRegisteredException;
use App\Models\Device;
use App\Services\Admin\WhatsAppMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function successfulPdfWwebjsPayload(): array
{
    return [
        'success' => true,
        'message' => [
            'id' => ['_serialized' => 'pdf-message-1'],
        ],
    ];
}

test('whatsapp PDF document keeps registration verification and sends message media as base64', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    config()->set('services.whatsapp_api.key', 'whatsapp-key');
    config()->set('services.whatsapp_api.message_delay_seconds', 0);

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => true,
        ]),
        'https://wa.test/client/sendMessage/main_session' => Http::response(successfulPdfWwebjsPayload()),
    ]);

    $pdf = "%PDF-1.7\ncertificate-document";
    $filename = 'شهادة-سورة-مريم.pdf';

    $messaging = app(WhatsAppMessagingService::class);
    $messaging->assertHasEligibleRecipients(['079 000 0111']);
    $messaging->sendPdfDocument(
        ['079 000 0111'],
        'شهادة الإنجاز',
        $pdf,
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
        && $request['content']['mimetype'] === 'application/pdf'
        && $request['content']['data'] === base64_encode($pdf)
        && $request['content']['filename'] === $filename
        && $request['content']['filesize'] === strlen($pdf)
        && $request['options']['caption'] === 'شهادة الإنجاز'
        && $request['options']['sendMediaAsDocument'] === true);
});

test('whatsapp PDF document does not send media when the personal number is not registered', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');

    Device::factory()->connected()->create(['session_id' => 'main_session']);

    Http::fake([
        'https://wa.test/client/isRegisteredUser/main_session' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    expect(fn () => app(WhatsAppMessagingService::class)->assertHasEligibleRecipients(
        ['079 000 0111'],
    ))->toThrow(WhatsAppRecipientNotRegisteredException::class);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('whatsapp PDF document rejects invalid bytes before contacting the api', function () {
    config()->set('services.whatsapp_api.url', 'https://wa.test');
    Http::fake();

    expect(fn () => app(WhatsAppMessagingService::class)->sendPdfDocument(
        ['079 000 0111'],
        'شهادة الإنجاز',
        'not-a-pdf',
        'شهادة-سورة-مريم.pdf',
    ))->toThrow(InvalidArgumentException::class, 'not a valid PDF');

    Http::assertNothingSent();
});
