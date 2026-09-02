<?php

use App\Models\Certificate;
use App\Models\Student;
use App\Services\Admin\CertificatePdfRenderer;
use App\Services\Admin\StudentCertificateService;
use Spatie\LaravelPdf\Drivers\PdfDriver;
use Spatie\LaravelPdf\Enums\Orientation;
use Spatie\LaravelPdf\PdfOptions;

/** @return array<string, mixed> */
function certificatePdfRendererPayload(): array
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
        'labels' => config('certificates.labels', []),
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

test('certificate PDF renderer uses the real certificate view and A4 landscape settings', function () {
    $student = new Student;
    $student->forceFill(['id' => 7]);
    $certificate = new Certificate;
    $certificate->forceFill(['id' => 11, 'student_id' => 7]);

    $certificates = Mockery::mock(StudentCertificateService::class);
    $certificates->shouldReceive('viewPayload')
        ->once()
        ->with($student, $certificate, true)
        ->andReturn(certificatePdfRendererPayload());

    $driver = Mockery::mock(PdfDriver::class);
    $driver->shouldReceive('generatePdf')
        ->once()
        ->withArgs(function (string $html, ?string $header, ?string $footer, PdfOptions $options): bool {
            return str_contains($html, 'طالب الاختبار')
                && str_contains($html, 'data:image/svg+xml;base64,')
                && $header === null
                && $footer === null
                && $options->format === 'a4'
                && $options->orientation === Orientation::Landscape->value
                && $options->margins === [
                    'top' => 0.0,
                    'right' => 0.0,
                    'bottom' => 0.0,
                    'left' => 0.0,
                    'unit' => 'mm',
                ];
        })
        ->andReturn('%PDF-1.7 certificate-document');
    app()->instance(PdfDriver::class, $driver);

    $rendered = (new CertificatePdfRenderer($certificates))->render($student, $certificate);

    expect($rendered)->toBe('%PDF-1.7 certificate-document');
});

test('certificate PDF renderer rejects invalid document bytes', function () {
    $student = new Student;
    $student->forceFill(['id' => 7]);
    $certificate = new Certificate;
    $certificate->forceFill(['student_id' => 7]);

    $certificates = Mockery::mock(StudentCertificateService::class);
    $certificates->shouldReceive('viewPayload')->once()->andReturn(certificatePdfRendererPayload());

    $driver = Mockery::mock(PdfDriver::class);
    $driver->shouldReceive('generatePdf')->once()->andReturn('not-a-pdf');
    app()->instance(PdfDriver::class, $driver);

    expect(fn () => (new CertificatePdfRenderer($certificates))->render($student, $certificate))
        ->toThrow(RuntimeException::class, 'invalid PDF document');
});
