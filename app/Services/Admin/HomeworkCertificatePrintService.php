<?php

namespace App\Services\Admin;

use App\Models\Certificate;
use App\Models\Homework;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Services\System\CertificateAchievementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeworkCertificatePrintService
{
    public function __construct(
        private readonly HomeworkCertificateEligibilityService $eligibility,
        private readonly CertificateAchievementService $achievements,
        private readonly StudentCertificateService $certificates,
        private readonly CertificatePdfRenderer $pdfRenderer,
    ) {}

    public function options(Homework $homework): array
    {
        return array_column($this->candidates($homework), 'option');
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<string>  $types
     */
    public function print(Homework $homework, array $studentIds, array $types): string
    {
        $matches = array_filter($this->candidates($homework), static fn (array $candidate): bool => ($studentIds === [] || in_array($candidate['option']['student_id'], $studentIds, true))
            && ($types === [] || in_array($candidate['option']['type_key'], $types, true)));

        if ($matches === []) {
            throw ValidationException::withMessages(['selection' => __('homeworks.no_due_certificates')]);
        }

        $pages = [];

        foreach ($matches as $candidate) {
            $certificate = $this->certificates->issueFromCompletion(
                $candidate['transaction'],
                (int) $candidate['checkpoint']->id,
            );

            if ($certificate->status !== Certificate::STATUS_VALID) {
                throw ValidationException::withMessages(['selection' => __('homeworks.certificate_print_selection_changed')]);
            }

            $pages[] = ['student' => $candidate['student'], 'certificate' => $certificate];
        }

        $pdf = $this->pdfRenderer->renderBatch($pages);
        $printedAt = now();
        $printedBy = Auth::id();

        DB::table('homework_certificate_prints')->upsert(
            array_map(static fn (array $page): array => [
                'homework_id' => $homework->id,
                'certificate_id' => $page['certificate']->id,
                'printed_at' => $printedAt,
                'printed_by' => $printedBy,
            ], $pages),
            ['homework_id', 'certificate_id'],
            ['printed_at', 'printed_by'],
        );

        return $pdf;
    }

    /**
     * @return list<array{
     *     transaction: StudentPointTransaction, checkpoint: PlanPoint, student: Student,
     *     option: array{student_id: int, student_name: string, plan_point_id: int, type_key: string, type_label: string, printed: bool}
     * }>
     */
    private function candidates(Homework $homework): array
    {
        $targets = collect($this->eligibility->targets($homework));
        if ($targets->isEmpty()) {
            return [];
        }

        $existing = Certificate::query()
            ->whereIn('student_id', $targets->pluck('student.id')->unique()->values())
            ->whereIn('plan_point_id', $targets->pluck('checkpoint.id')->unique()->values())
            ->get(['id', 'student_id', 'plan_point_id', 'status', 'achievement_type', 'achievement_name'])
            ->keyBy(static fn (Certificate $certificate): string => "{$certificate->student_id}:{$certificate->plan_point_id}");
        $printedIds = DB::table('homework_certificate_prints')
            ->where('homework_id', $homework->id)
            ->pluck('certificate_id')
            ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true]);

        $candidates = [];
        foreach ($targets as $target) {
            $student = $target['student'];
            $checkpoint = $target['checkpoint'];
            $certificate = $existing->get("{$student->id}:{$checkpoint->id}");
            if ($certificate !== null && $certificate->status !== Certificate::STATUS_VALID) {
                continue;
            }

            $achievement = $certificate === null
                ? $this->achievements->resolve($checkpoint)
                : ['type' => (string) $certificate->achievement_type, 'name' => (string) $certificate->achievement_name];
            if ($achievement === null) {
                continue;
            }

            $type = $achievement['type'];
            $name = $achievement['name'];
            $candidates[] = [
                ...$target,
                'option' => [
                    'student_id' => (int) $student->id,
                    'student_name' => (string) $student->full_name,
                    'plan_point_id' => (int) $checkpoint->id,
                    'type_key' => hash('sha256', $type."\0".$name),
                    'type_label' => __('certificates.types.'.$type).' — '.$this->achievements->displayName($type, $name),
                    'printed' => $certificate !== null && $printedIds->has((int) $certificate->id),
                ],
            ];
        }

        return $candidates;
    }
}
