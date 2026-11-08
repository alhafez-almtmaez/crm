<?php

namespace App\Services\Admin;

use App\Models\Certificate;
use App\Models\Student;
use LogicException;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

class CertificatePdfRenderer
{
    private const PDF_SIGNATURE = '%PDF-';

    public function __construct(
        private readonly StudentCertificateService $certificates,
    ) {}

    /**
     * Generate the same A4 landscape PDF used by the certificate download action.
     */
    public function render(Student $student, Certificate $certificate): string
    {
        return $this->renderView('certificates.show', [
            'certificate' => $this->page($student, $certificate),
        ]);
    }

    /** @param list<array{student: Student, certificate: Certificate}> $pages */
    public function renderBatch(array $pages): string
    {
        $certificates = [];
        $sharedImageClasses = [];

        foreach ($pages as $page) {
            $certificate = $this->page($page['student'], $page['certificate'], includeStylesheet: false);
            foreach ($certificate['images'] as $source) {
                if ($source !== '' && ! isset($sharedImageClasses[$source])) {
                    $sharedImageClasses[$source] = 'certificate-batch-image-'.count($sharedImageClasses);
                }
            }
            $certificates[] = $certificate;
        }

        return $this->renderView('certificates.batch', [
            'certificates' => $certificates,
            'stylesheet' => $this->certificates->pdfStylesheetForDesigns(array_column($certificates, 'design')),
            'sharedImageClasses' => $sharedImageClasses,
        ]);
    }

    private function page(Student $student, Certificate $certificate, bool $includeStylesheet = true): array
    {
        if ((int) $certificate->student_id !== (int) $student->getKey()) {
            throw new LogicException('The certificate does not belong to the supplied student.');
        }

        return $this->certificates->viewPayload($student, $certificate, pdf: true, includeStylesheet: $includeStylesheet);
    }

    private function renderView(string $view, array $data): string
    {
        $bytes = Pdf::view($view, $data)
            ->format('a4')
            ->landscape()
            ->margins(0, 0, 0, 0)
            ->generatePdfContent();

        if (! str_starts_with($bytes, self::PDF_SIGNATURE)) {
            throw new RuntimeException('The certificate renderer returned an invalid PDF document.');
        }

        return $bytes;
    }
}
