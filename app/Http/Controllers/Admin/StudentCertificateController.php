<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateRevokeRequest;
use App\Http\Requests\Admin\CertificateStoreRequest;
use App\Models\Certificate;
use App\Models\Student;
use App\Services\Admin\AdminDataScopeService;
use App\Services\Admin\StudentCertificateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class StudentCertificateController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly StudentCertificateService $service,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:students.view', only: ['index', 'show', 'pdf']),
            new Middleware('can:students.update', only: ['store', 'redesign']),
            new Middleware('can:certificates.revoke', only: ['revoke']),
        ];
    }

    public function index(Student $student): Response
    {
        $this->dataScope->abortUnlessCanAccessStudent($student);

        return Inertia::render('Admin/Students/Certificates', $this->service->indexPayload($student));
    }

    public function store(CertificateStoreRequest $request, Student $student): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessStudent($student);

        $certificate = $this->service->issue(
            $student,
            (int) $request->validated('plan_point_id'),
        );

        return response()->json([
            'message' => __('certificates.issued_successfully'),
            'certificate' => $this->service->listItem($student, $certificate),
        ], 201);
    }

    public function show(Student $student, Certificate $certificate): View
    {
        $this->authorizeNestedCertificate($student, $certificate);

        return view('certificates.show', [
            'certificate' => $this->service->viewPayload($student, $certificate),
        ]);
    }

    public function redesign(Student $student, Certificate $certificate): JsonResponse
    {
        $this->authorizeNestedCertificate($student, $certificate);

        $certificate = $this->service->redesign($student, $certificate);

        return response()->json([
            'message' => __('certificates.redesigned_successfully'),
            'certificate' => $this->service->listItem($student, $certificate),
        ]);
    }

    public function revoke(
        CertificateRevokeRequest $request,
        Student $student,
        Certificate $certificate,
    ): JsonResponse {
        $this->authorizeNestedCertificate($student, $certificate);

        $certificate = $this->service->revoke(
            $student,
            $certificate,
            (string) $request->validated('revoked_reason'),
        );

        return response()->json([
            'message' => __('certificates.revoked_successfully'),
            'certificate' => $this->service->listItem($student, $certificate),
        ]);
    }

    public function pdf(Student $student, Certificate $certificate): PdfBuilder
    {
        $this->authorizeNestedCertificate($student, $certificate);

        return Pdf::view('certificates.show', [
            'certificate' => $this->service->viewPayload($student, $certificate, pdf: true),
        ])
            ->name("certificate-{$certificate->certificate_number}.pdf")
            ->format('a4')
            ->landscape()
            ->margins(0, 0, 0, 0)
            ->download();
    }

    private function authorizeNestedCertificate(Student $student, Certificate $certificate): void
    {
        $this->dataScope->abortUnlessCanAccessStudent($student);
        abort_unless((int) $certificate->student_id === (int) $student->id, 404);
    }
}
