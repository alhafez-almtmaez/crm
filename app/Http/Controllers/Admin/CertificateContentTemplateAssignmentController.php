<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateContentTemplateAssignmentUpdateRequest;
use App\Models\Center;
use App\Models\CertificateContentTemplateAssignment;
use App\Services\Admin\AdminDataScopeService;
use App\Services\System\CertificateContentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CertificateContentTemplateAssignmentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CertificateContentTemplateService $service,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:certificate_designs.update'),
        ];
    }

    public function update(CertificateContentTemplateAssignmentUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizeScope(
            (string) $validated['scope_type'],
            isset($validated['center_id']) ? (int) $validated['center_id'] : null,
        );
        $assignment = $this->service->upsertAssignment($validated);

        return response()->json([
            'message' => __('certificates.content_template_assignment_updated'),
            'assignment' => $this->service->assignmentPayload($assignment),
        ]);
    }

    public function destroy(CertificateContentTemplateAssignment $assignment): JsonResponse
    {
        $this->authorizeScope(
            (string) $assignment->scope_type,
            $assignment->center_id !== null ? (int) $assignment->center_id : null,
        );
        $this->service->deleteAssignment($assignment);

        return response()->json([
            'message' => __('certificates.content_template_assignment_deleted'),
        ]);
    }

    private function authorizeScope(string $scopeType, ?int $centerId): void
    {
        if ($scopeType !== CertificateContentTemplateAssignment::SCOPE_CENTER) {
            abort_unless($this->dataScope->isAdmin(), 403);

            return;
        }

        $center = $centerId !== null ? Center::query()->find($centerId) : null;
        abort_if($center === null, 404);
        $this->dataScope->abortUnlessCanAccessCenter($center);
    }
}
