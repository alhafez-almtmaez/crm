<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Student;
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

    public function index(string $student_slug, string $portal_id): InertiaResponse|RedirectResponse|Response
    {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        if ($redirect = $this->canonicalRedirect($student, $student_slug)) {
            return $redirect;
        }

        return Inertia::render('Certificates/StudentGallery', [
            'portal' => $this->portals->payload($student),
        ]);
    }

    public function show(
        string $student_slug,
        string $portal_id,
        string $certificate_public_id,
    ): View|RedirectResponse|Response {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        $certificate = $this->portals->findValidCertificate($student, $certificate_public_id);
        if ($certificate === null) {
            return $this->notFoundResponse();
        }

        if ($redirect = $this->canonicalRedirect($student, $student_slug, $certificate, 'show')) {
            return $redirect;
        }

        $payload = $this->certificates->viewPayload($student, $certificate);
        $payload['back_url'] = $this->portals->url($student);
        $payload['pdf_url'] = $this->portals->pdfUrl($student, $certificate);

        return view('certificates.show', ['certificate' => $payload]);
    }

    public function pdf(
        string $student_slug,
        string $portal_id,
        string $certificate_public_id,
    ): PdfBuilder|RedirectResponse|Response {
        $student = $this->portals->findStudent($portal_id);
        if ($student === null) {
            return $this->notFoundResponse();
        }

        $certificate = $this->portals->findValidCertificate($student, $certificate_public_id);
        if ($certificate === null) {
            return $this->notFoundResponse();
        }

        if ($redirect = $this->canonicalRedirect($student, $student_slug, $certificate, 'pdf')) {
            return $redirect;
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

    private function notFoundResponse(): Response
    {
        return response()->view('certificates.portal-not-found', status: 404);
    }

    private function canonicalRedirect(
        Student $student,
        string $requestedSlug,
        ?Certificate $certificate = null,
        ?string $action = null,
    ): ?RedirectResponse {
        if (hash_equals($this->portals->slug($student), $requestedSlug)) {
            return null;
        }

        $url = match ($action) {
            'show' => $this->portals->previewUrl($student, $certificate),
            'pdf' => $this->portals->pdfUrl($student, $certificate),
            default => $this->portals->url($student),
        };

        return redirect()->to($url);
    }
}
