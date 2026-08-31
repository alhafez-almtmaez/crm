<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeworkIndexRequest;
use App\Http\Requests\Admin\HomeworkStoreRequest;
use App\Http\Requests\Admin\HomeworkUpdateRequest;
use App\Models\Homework;
use App\Models\Student;
use App\Services\Admin\AdminDataScopeService;
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
    public function __construct(
        private readonly HomeworkService $service,
        private readonly AdminDataScopeService $dataScope,
    ) {}

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

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.daily-follow-up.index', [
            ...$request->only(['center_id', 'group_id', 'date']),
            'section' => 'homework',
        ]);
    }

    public function edit(Homework $homework): Response
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        $homework->loadMissing(['group.center', 'center']);

        $centerId = $homework->group?->center_id ?? $homework->center_id;
        $centerName = $homework->group?->center?->name ?? $homework->center?->name;

        return Inertia::render('Admin/Homeworks/Edit', [
            'homework' => [
                'id' => $homework->id,
                'center_id' => $centerId,
                'center_name' => $centerName,
                'group_id' => $homework->group_id,
                'group_name' => $homework->group?->name,
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
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        $this->service->update($homework, $request->validated());

        return redirect()
            ->route('admin.homeworks.index')
            ->with('success', __('homeworks.updated_successfully'));
    }

    public function destroy(Homework $homework): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        $this->service->delete($homework);

        return response()->json([
            'message' => __('homeworks.deleted_successfully'),
        ]);
    }

    public function pdf(Homework $homework): PdfBuilder
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        $payload = $this->service->pdfPayload($homework);

        return Pdf::view('pdf.homework', $payload)
            ->name($payload['file_name'])
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->download();
    }

    public function pointHistory(Student $student): JsonResponse
    {
        $this->dataScope->abortUnlessCanAccessStudent($student);

        return response()->json([
            'data' => $this->service->pointHistory($student),
        ]);
    }
}
