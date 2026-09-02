<?php

use App\Models\Certificate;
use App\Models\Student;
use App\Services\Admin\CertificateImageRenderer;
use App\Services\Admin\StudentCertificateService;
use Illuminate\Support\Facades\Http;

/** @return array<string, mixed> */
function certificateImageRendererPayload(): array
{
    return [
        'page_title' => 'شهادة إنجاز',
        'title' => 'شهادة إنجاز',
        'quote_first' => 'لولا المشقة ساد الناس كلهم',
        'quote_second' => 'الجود يفقر والإقدام قتال',
        'project_name' => 'مشروع الحافظ المتميز',
        'center_name' => 'مركز السلام القرآني',
        'show_center_manager_signature' => true,
        'student_name' => 'طالب الاختبار',
        'achievement_intro' => 'وذلك لإنجازه',
        'achievement_label' => 'سورة',
        'achievement_name' => 'مريم',
        'achievement_suffix' => 'بإتقان عال',
        'closing_text' => 'نسأل الله له التوفيق والثبات.',
        'center_manager_title' => 'مدير المركز',
        'project_manager_title' => 'مدير المشروع',
        'date_title' => 'تاريخ الإنجاز',
        'hijri_date' => '١٥ رَبِيع الأَوَّل ١٤٤٨',
        'gregorian_date' => '٢٠٢٦/٠٩/٠٢',
        'certificate_number' => 'HMT-2026-ABCDEFGH',
        'intro_before_project' => 'تتقدم إدارة',
        'intro_after_center' => 'بالتهنئة الحارة',
        'content_template' => null,
        'labels' => [
            'tools' => 'أدوات الشهادة',
            'back' => 'رجوع',
            'download_pdf' => 'تنزيل PDF',
            'print' => 'طباعة',
            'left_logo' => 'شعار المركز',
            'right_logo' => 'شعار المشروع',
            'poem' => 'بيت شعر',
            'center_signature' => 'توقيع مدير المركز',
            'center_stamp' => 'ختم المركز',
            'project_signature' => 'توقيع مدير المشروع',
            'project_stamp' => 'ختم المشروع',
            'achievement_date' => 'تاريخ الإنجاز',
            'hijri' => 'هجري',
            'gregorian' => 'ميلادي',
            'verify_certificate' => 'تحقق من الشهادة',
            'certificate_number' => 'رقم الشهادة',
        ],
        'design' => [],
        'stylesheet_url' => 'data:text/css;base64,'.base64_encode('.certificate{background:#fff}'),
        'font_preload_urls' => [],
        'images' => [
            'frame' => '',
            'left_logo' => '',
            'right_logo' => '',
            'center_stamp' => '',
            'center_signature' => '',
            'project_stamp' => '',
            'project_signature' => '',
        ],
        'pdf_mode' => true,
        'back_url' => 'https://example.test/admin/students/7/certificates',
        'pdf_url' => 'https://example.test/admin/students/7/certificates/01/pdf',
        'qr_code_data_uri' => 'data:image/svg+xml;base64,'.base64_encode('<svg></svg>'),
        'qr_foreground_color' => '#09232A',
        'verification_url' => 'https://example.test/verify/test-id',
    ];
}

test('certificate image renderer sends self contained certificate html to cloudflare and returns png bytes', function () {
    config()->set('laravel-pdf.cloudflare.api_token', 'cloudflare-token');
    config()->set('laravel-pdf.cloudflare.account_id', 'account-id');

    $student = new Student;
    $student->forceFill(['id' => 7]);
    $certificate = new Certificate;
    $certificate->forceFill([
        'id' => 11,
        'student_id' => 7,
    ]);

    $certificates = Mockery::mock(StudentCertificateService::class);
    $certificates->shouldReceive('viewPayload')
        ->once()
        ->with($student, $certificate, true)
        ->andReturn(certificateImageRendererPayload());

    $png = "\x89PNG\r\n\x1a\nrendered-image";

    Http::fake([
        'https://api.cloudflare.com/client/v4/accounts/account-id/browser-rendering/screenshot' => Http::response(
            $png,
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $rendered = (new CertificateImageRenderer($certificates))->render($student, $certificate);

    expect($rendered)->toBe($png);

    Http::assertSent(function ($request): bool {
        $html = $request['html'] ?? '';

        return $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-id/browser-rendering/screenshot'
            && $request->hasHeader('Authorization', 'Bearer cloudflare-token')
            && is_string($html)
            && str_contains($html, 'certificate-image-body')
            && str_contains($html, 'certificate-page--image')
            && str_contains($html, 'data:image/svg+xml;base64,')
            && str_contains($html, 'https://example.test/verify/test-id')
            && ($request['viewport']['width'] ?? null) === 1600
            && ($request['viewport']['height'] ?? null) === 1131
            && ($request['selector'] ?? null) === '.certificate'
            && ($request['screenshotOptions']['type'] ?? null) === 'png'
            && ($request['screenshotOptions']['fullPage'] ?? null) === false;
    });
});

test('certificate image renderer rejects a successful non png cloudflare response', function () {
    config()->set('laravel-pdf.cloudflare.api_token', 'cloudflare-token');
    config()->set('laravel-pdf.cloudflare.account_id', 'account-id');

    $student = new Student;
    $student->forceFill(['id' => 7]);
    $certificate = new Certificate;
    $certificate->forceFill(['student_id' => 7]);

    $certificates = Mockery::mock(StudentCertificateService::class);
    $certificates->shouldReceive('viewPayload')
        ->once()
        ->andReturn(certificateImageRendererPayload());

    Http::fake([
        '*' => Http::response('{"success":false}', 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn () => (new CertificateImageRenderer($certificates))->render($student, $certificate))
        ->toThrow(RuntimeException::class, 'invalid certificate PNG');
});
