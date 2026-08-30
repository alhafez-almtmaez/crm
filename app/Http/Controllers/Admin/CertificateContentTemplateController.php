<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateContentTemplateStoreRequest;
use App\Http\Requests\Admin\CertificateContentTemplateUpdateRequest;
use App\Models\CertificateContentTemplate;
use App\Services\Admin\AdminDataScopeService;
use App\Services\System\CertificateContentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class CertificateContentTemplateController extends Controller implements HasMiddleware
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

    public function store(CertificateContentTemplateStoreRequest $request): JsonResponse
    {
        $this->authorizeGlobalTemplateMutation();
        $template = $this->service->create($request->validated(), Auth::id());

        return response()->json([
            'message' => __('certificates.content_template_created'),
            'template' => $this->service->templatePayload($template),
        ], 201);
    }

    public function update(
        CertificateContentTemplateUpdateRequest $request,
        CertificateContentTemplate $certificateContentTemplate,
    ): JsonResponse {
        $this->authorizeGlobalTemplateMutation();
        $template = $this->service->update(
            $certificateContentTemplate,
            $request->validated(),
            Auth::id(),
        );

        return response()->json([
            'message' => __('certificates.content_template_updated'),
            'template' => $this->service->templatePayload($template),
        ]);
    }

    public function destroy(CertificateContentTemplate $certificateContentTemplate): JsonResponse
    {
        $this->authorizeGlobalTemplateMutation();
        $this->service->delete($certificateContentTemplate);

        return response()->json([
            'message' => __('certificates.content_template_deleted'),
        ]);
    }

    private function authorizeGlobalTemplateMutation(): void
    {
        abort_unless($this->dataScope->isAdmin(), 403);
    }
}
