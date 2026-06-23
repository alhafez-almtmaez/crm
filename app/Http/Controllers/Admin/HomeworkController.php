<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeworkIndexRequest;
use App\Http\Requests\Admin\HomeworkStoreRequest;
use App\Http\Requests\Admin\HomeworkUpdateRequest;
use App\Models\Homework;
use App\Models\Student;
use App\Services\Admin\HomeworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class HomeworkController extends Controller implements HasMiddleware
{
    public function __construct(private readonly HomeworkService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:homeworks.view', only: ['index', 'records', 'pointHistory', 'pdf']),
            new Middleware('can:homeworks.create', only: ['create', 'store']),
            new Middleware('can:homeworks.update', only: ['edit', 'update']),
            new Middleware('can:homeworks.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Homeworks');
    }

    public function records(HomeworkIndexRequest $request): JsonResponse
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

    public function create(Request $request): Response
    {
        $query = $request->validate([
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $centerId = isset($query['center_id']) ? (int) $query['center_id'] : null;
        $date = isset($query['date']) ? (string) $query['date'] : null;

        return Inertia::render('Admin/Homeworks/Create', [
            'centers' => $this->service->centerOptions(),
            ...$this->service->createFormPayload($centerId, $date),
        ]);
    }

    public function edit(Homework $homework): Response
    {
        $homework->load('center:id,name');

        return Inertia::render('Admin/Homeworks/Edit', [
            'homework' => [
                'id' => $homework->id,
                'center_id' => $homework->center_id,
                'center_name' => $homework->center?->name,
                'date' => $homework->date?->format('Y-m-d'),
            ],
            'centers' => $this->service->centerOptions(),
            'students' => $this->service->editStudentRows($homework),
        ]);
    }

    public function store(HomeworkStoreRequest $request): RedirectResponse
    {
        $homework = $this->service->create($request->validated());

        return redirect()
            ->route('admin.homeworks.edit', $homework)
            ->with('success', __('homeworks.created_successfully'));
    }

    public function update(HomeworkUpdateRequest $request, Homework $homework): RedirectResponse
    {
        $this->service->update($homework, $request->validated());

        return redirect()
            ->route('admin.homeworks.index')
            ->with('success', __('homeworks.updated_successfully'));
    }

    public function destroy(Homework $homework): JsonResponse
    {
        $this->service->delete($homework);

        return response()->json([
            'message' => __('homeworks.deleted_successfully'),
        ]);
    }

    public function pdf(Homework $homework): PdfBuilder
    {
        $payload = $this->service->pdfPayload($homework);

        return Pdf::view('pdf.homework', $payload)
            ->name($payload['file_name'])
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->download();
    }

    public function pointHistory(Student $student): JsonResponse
    {
        return response()->json([
            'data' => $this->service->pointHistory($student),
        ]);
    }
}
