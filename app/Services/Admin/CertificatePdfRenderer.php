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
        if ((int) $certificate->student_id !== (int) $student->getKey()) {
            throw new LogicException('The certificate does not belong to the supplied student.');
        }

        $bytes = Pdf::view('certificates.show', [
            'certificate' => $this->certificates->viewPayload($student, $certificate, pdf: true),
        ])
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
