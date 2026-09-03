<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Services\Admin\StudentCertificateService;
use Illuminate\Console\Command;

class SyncCertificateAchievementDates extends Command
{
    protected $signature = 'certificates:sync-achievement-dates
        {--apply : Persist the proven achievement dates}
        {--student-id= : Limit the audit to one student ID}
        {--certificate= : Limit by certificate ULID or certificate number}';

    protected $description = 'Audit or correct certificate dates using the first same-plan completion evidence';

    public function handle(StudentCertificateService $certificates): int
    {
        $studentId = $this->option('student-id');
        if ($studentId !== null && (! ctype_digit((string) $studentId) || (int) $studentId < 1)) {
            $this->error('The --student-id option must be a positive integer.');

            return self::INVALID;
        }

        $certificateReference = trim((string) ($this->option('certificate') ?? ''));
        $apply = (bool) $this->option('apply');
        $query = Certificate::query()
            ->when($studentId !== null, static fn ($builder) => $builder->where('student_id', (int) $studentId))
            ->when($certificateReference !== '', static function ($builder) use ($certificateReference): void {
                $builder->where(static function ($reference) use ($certificateReference): void {
                    $reference
                        ->where('ulid', $certificateReference)
                        ->orWhere('certificate_number', $certificateReference);
                });
            });
        $counts = [
            'scanned' => 0,
            'needs_update' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'no_evidence' => 0,
            'unlinked' => 0,
            'processing' => 0,
        ];
        $examples = [];

        $query->orderBy('id')->chunkById(200, function ($rows) use (
            $certificates,
            $apply,
            &$counts,
            &$examples,
        ): void {
            foreach ($rows as $certificate) {
                $result = $certificates->synchronizeAchievementDate($certificate, $apply);
                $counts['scanned']++;
                $counts[$result['status']]++;

                if (in_array($result['status'], ['needs_update', 'updated'], true)
                    && count($examples) < 15) {
                    $examples[] = [
                        $result['certificate_number'],
                        $result['student_id'],
                        $result['previous_achieved_at'] ?? '—',
                        $result['corrected_achieved_at'] ?? '—',
                    ];
                }
            }
        });

        $this->components->info($apply
            ? 'Certificate achievement dates synchronized.'
            : 'Dry run complete; no certificate was changed.');
        $this->table(['Metric', 'Count'], collect($counts)->map(
            static fn (int $count, string $metric): array => [$metric, $count],
        )->values()->all());
        if ($examples !== []) {
            $this->newLine();
            $this->table(
                ['Certificate', 'Student ID', 'Previous', $apply ? 'Applied' : 'Proposed'],
                $examples,
            );
        }
        if (! $apply && $counts['needs_update'] > 0) {
            $this->newLine();
            $this->warn('Run the same command with --apply to persist these corrections.');
        }

        return self::SUCCESS;
    }
}
