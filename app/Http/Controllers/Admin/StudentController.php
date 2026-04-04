<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StudentsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentCongratulatoryRequest;
use App\Http\Requests\Admin\StudentExportRequest;
use App\Http\Requests\Admin\StudentFreezeRequest;
use App\Http\Requests\Admin\StudentImportRequest;
use App\Http\Requests\Admin\StudentIndexRequest;
use App\Http\Requests\Admin\StudentStoreRequest;
use App\Http\Requests\Admin\StudentUpdateRequest;
use App\Models\Student;
use App\Services\Admin\ActivityLogService;
use App\Services\Admin\StudentCommunicationService;
use App\Services\Admin\GroupService;
use App\Services\Admin\StudentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly StudentService $studentService,
        private readonly StudentCommunicationService $studentCommunicationService,
        private readonly GroupService $groupService,
        private readonly ActivityLogService $activityLogService,
    )
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:students.view', only: ['index', 'records']),
            new Middleware('can:students.view', only: ['activityLogs']),
            new Middleware('can:students.view', only: ['export']),
            new Middleware('can:students.create', only: ['create', 'store']),
            new Middleware('can:students.update', only: ['edit', 'update']),
            new Middleware('can:students.update', only: ['import']),
            new Middleware('can:students.update', only: ['freeze', 'unfreeze', 'congratulatory']),
            new Middleware('can:students.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Students', [
            'centers' => $this->studentService->centerOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Students/Create', [
            'centers' => $this->studentService->centerOptions(),
            'plans' => $this->studentService->planOptions(),
            'admins' => $this->studentService->adminOptions(),
            'canAssignAdmin' => (bool) Auth::user()?->hasRole('admin'),
        ]);
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Admin/Students/Edit', [
            'student' => [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'second_name' => $student->second_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'id_number' => $student->id_number,
                'parent_phone_number' => $student->parent_phone_number,
                'phone_number' => $student->phone_number,
                'email' => $student->email,
                'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                'center_id' => $student->center_id,
                'group_id' => $student->group_id,
                'plan_type_id' => $student->plan_type_id,
                'admin_id' => $student->admin_id,
                'is_active' => $student->is_active,
            ],
            'centers' => $this->studentService->centerOptions(),
            'plans' => $this->studentService->planOptions(),
            'admins' => $this->studentService->adminOptions(),
            'canAssignAdmin' => (bool) Auth::user()?->hasRole('admin'),
            'groups' => $student->center
                ? $this->groupService->listByCenter($student->center)
                : [],
        ]);
    }

    public function records(StudentIndexRequest $request): JsonResponse
    {
        $students = $this->studentService->list($request->validated());

        return response()->json([
            'data' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function store(StudentStoreRequest $request): RedirectResponse
    {
        $this->studentService->create($request->validated());

        return redirect()
            ->route('admin.students.index')
            ->with('success', __('students.created_successfully'));
    }

    public function update(StudentUpdateRequest $request, Student $student): RedirectResponse
    {
        $this->studentService->update($student, $request->validated());

        return redirect()
            ->route('admin.students.index')
            ->with('success', __('students.updated_successfully'));
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->studentService->delete($student);

        return response()->json([
            'message' => __('students.deleted_successfully'),
        ]);
    }

    public function activityLogs(Student $student): JsonResponse
    {
        return response()->json([
            'data' => $this->activityLogService->listForSubject(Student::class, $student->getKey()),
        ]);
    }

    public function freeze(StudentFreezeRequest $request, Student $student): JsonResponse
    {
        try {
            $this->studentCommunicationService->freeze($student, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('students.frozen_successfully'),
        ]);
    }

    public function unfreeze(Student $student): JsonResponse
    {
        try {
            $this->studentCommunicationService->unfreeze($student);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('students.unfrozen_successfully'),
        ]);
    }

    public function congratulatory(StudentCongratulatoryRequest $request, Student $student): JsonResponse
    {
        try {
            $this->studentCommunicationService->congratulatory($student, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('students.congratulatory_sent_successfully'),
        ]);
    }

    public function export(StudentExportRequest $request): BinaryFileResponse
    {
        $centerId = $request->validated('center_id');
        $rows = $this->studentService->exportRows($centerId !== null ? (int) $centerId : null);
        $fileName = 'students-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new StudentsExport($rows), $fileName);
    }

    public function import(StudentImportRequest $request): JsonResponse
    {
        $result = $this->studentService->importFile($request->file('file'));

        if ($result['updated'] === 0 && $result['errors'] !== []) {
            return response()->json([
                'message' => __('students.import_failed'),
                'errors' => [
                    'file' => $result['errors'],
                ],
            ], 422);
        }

        return response()->json([
            'message' => __('students.import_completed', [
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ]),
            'meta' => $result,
        ]);
    }
}
