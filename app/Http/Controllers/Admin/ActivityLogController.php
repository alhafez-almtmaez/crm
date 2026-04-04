<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLogIndexRequest;
use App\Services\Admin\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ActivityLogService $activityLogService)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:activity_logs.view'),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/ActivityLogs');
    }

    public function records(ActivityLogIndexRequest $request): JsonResponse
    {
        $logs = $this->activityLogService->list($request->validated());

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}

