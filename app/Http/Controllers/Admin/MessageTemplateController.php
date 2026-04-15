<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MessageTemplateIndexRequest;
use App\Http\Requests\Admin\MessageTemplateStoreRequest;
use App\Http\Requests\Admin\MessageTemplateUpdateRequest;
use App\Models\MessageTemplate;
use App\Services\Admin\MessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplateController extends Controller implements HasMiddleware
{
    public function __construct(private readonly MessageTemplateService $service)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:message_templates.view', only: ['index', 'records']),
            new Middleware('can:message_templates.create', only: ['create', 'store']),
            new Middleware('can:message_templates.update', only: ['edit', 'update']),
            new Middleware('can:message_templates.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/MessageTemplates');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/MessageTemplates/Create');
    }

    public function edit(MessageTemplate $messageTemplate): Response
    {
        return Inertia::render('Admin/MessageTemplates/Edit', [
            'template' => [
                'id' => $messageTemplate->id,
                'key' => $messageTemplate->key,
                'name' => $messageTemplate->name,
                'locale' => $messageTemplate->locale,
                'content' => $messageTemplate->content,
                'placeholders' => $messageTemplate->placeholders,
                'is_active' => $messageTemplate->is_active,
            ],
        ]);
    }

    public function records(MessageTemplateIndexRequest $request): JsonResponse
    {
        $rows = $this->service->list($request->validated());

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function store(MessageTemplateStoreRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('admin.message-templates.index')
            ->with('success', __('message_templates.created_successfully'));
    }

    public function update(MessageTemplateUpdateRequest $request, MessageTemplate $messageTemplate): RedirectResponse
    {
        $this->service->update($messageTemplate, $request->validated());

        return redirect()
            ->route('admin.message-templates.index')
            ->with('success', __('message_templates.updated_successfully'));
    }

    public function destroy(MessageTemplate $messageTemplate): JsonResponse
    {
        $this->service->delete($messageTemplate);

        return response()->json([
            'message' => __('message_templates.deleted_successfully'),
        ]);
    }
}
