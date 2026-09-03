<?php

namespace App\Http\Controllers;

use App\Services\Admin\StudentCertificateService;
use App\Services\System\StudentCertificatePortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class StudentCertificatePortalController extends Controller
{
    public function __construct(
        private readonly StudentCertificatePortalService $portals,
        private readonly StudentCertificateService $certificates,
    ) {}

    public function index(string $portal_id): InertiaResponse|Response
    {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        $portal = $this->portals->payload($student);
        $title = 'شهادات '.$portal['student_name'].' | '.$portal['brand_name'];
        $description = 'شهادات الإنجاز الخاصة بـ '.$portal['student_name'].' والصادرة عن '.$portal['brand_name'].'.';

        return Inertia::render('Certificates/StudentGallery', [
            'portal' => $portal,
        ])->withViewData([
            'pageMeta' => [
                'title' => $title,
                'description' => $description,
                'url' => $portal['portal_url'],
                'image' => $portal['logo_url'],
                'image_alt' => 'شعار '.$portal['brand_name'],
                'locale' => 'ar_AR',
                'type' => 'website',
            ],
        ]);
    }

    public function show(
        string $portal_id,
        string $certificate_public_id,
    ): View|Response {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        $certificate = $this->portals->findValidCertificate($student, $certificate_public_id);
        if ($certificate === null) {
            return $this->notFoundResponse();
        }

        $payload = $this->certificates->viewPayload($student, $certificate);
        $payload['back_url'] = $this->portals->url($student);
        $payload['pdf_url'] = $this->portals->pdfUrl($student, $certificate);

        return view('certificates.show', ['certificate' => $payload]);
    }

    public function pdf(
        string $portal_id,
        string $certificate_public_id,
    ): PdfBuilder|Response {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        $certificate = $this->portals->findValidCertificate($student, $certificate_public_id);
        if ($certificate === null) {
            return $this->notFoundResponse();
        }

        $payload = $this->certificates->viewPayload($student, $certificate, pdf: true);
        $payload['back_url'] = $this->portals->url($student);
        $payload['pdf_url'] = $this->portals->pdfUrl($student, $certificate);
        $filename = $this->portals->pdfFilename($student, $certificate);
        $contentDisposition = (new ResponseHeaderBag)->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            'certificate.pdf',
        );

        return Pdf::view('certificates.show', ['certificate' => $payload])
            ->name($filename)
            ->format('a4')
            ->landscape()
            ->margins(0, 0, 0, 0)
            ->download()
            ->headers([
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Content-Disposition' => $contentDisposition,
            ]);
    }

    public function legacyIndex(string $student_slug, string $portal_id): RedirectResponse|Response
    {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        return redirect()->to($this->portals->url($student));
    }

    public function legacyShow(
        string $student_slug,
        string $portal_id,
        string $certificate_public_id,
    ): RedirectResponse|Response {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        $certificate = $this->portals->findValidCertificate($student, $certificate_public_id);
        if ($certificate === null) {
            return $this->notFoundResponse();
        }

        return redirect()->to($this->portals->previewUrl($student, $certificate));
    }

    public function legacyPdf(
        string $student_slug,
        string $portal_id,
        string $certificate_public_id,
    ): RedirectResponse|Response {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        $certificate = $this->portals->findValidCertificate($student, $certificate_public_id);
        if ($certificate === null) {
            return $this->notFoundResponse();
        }

        return redirect()->to($this->portals->pdfUrl($student, $certificate));
    }

    private function notFoundResponse(): Response
    {
        return response()->view('certificates.portal-not-found', status: 404);
    }
}
