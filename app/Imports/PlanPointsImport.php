<?php

namespace App\Imports;

use App\Models\Plan;
use App\Models\PlanPoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithLimit;

class PlanPointsImport implements SkipsEmptyRows, ToCollection, WithColumnLimit, WithLimit
{
    private int $imported = 0;

    private int $skipped = 0;

    /** @var array<int, string> */
    private array $errors = [];

    private ?bool $usesIdColumn = null;

    public function __construct(private readonly Plan $plan) {}

    public function collection(Collection $rows): void
    {
        $payloads = [];
        $sortOrder = 1;

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 1;
            $values = $row instanceof Collection ? $row->values()->all() : (array) $row;

            if ($this->isHeadingRow($values)) {
                $this->usesIdColumn ??= $this->headingHasIdColumn($values);

                continue;
            }

            $usesIdColumn = $this->usesIdColumn();
            $offset = $usesIdColumn ? 1 : 0;

            $payload = [
                'id' => $usesIdColumn ? $this->intOrNull($values[0] ?? null) : null,
                'plan_id' => $this->plan->id,
                'sort_order' => $sortOrder,
                'name' => $this->nullIfEmpty($values[$offset] ?? null),
                'points' => $this->intOrNull($values[$offset + 1] ?? null),
                'requires_certificate' => $this->boolValue($values[$offset + 2] ?? null),
                'surah_name' => $this->nullIfEmpty($values[$offset + 3] ?? null),
                'part_name' => $this->nullIfEmpty($values[$offset + 4] ?? null),
                'three_parts' => $this->nullIfEmpty($values[$offset + 5] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($payload['name'] === null) {
                $this->markSkipped("Line {$lineNumber}: plan point name is required.");

                continue;
            }

            $validator = Validator::make($payload, [
                'id' => ['nullable', 'integer', 'min:1'],
                'plan_id' => ['required', 'integer'],
                'sort_order' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'string', 'max:255'],
                'points' => ['nullable', 'integer', 'min:0', 'max:999999'],
                'requires_certificate' => ['boolean'],
                'surah_name' => ['nullable', 'string', 'max:255'],
                'part_name' => ['nullable', 'string', 'max:255'],
                'three_parts' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $this->markSkipped("Line {$lineNumber}: ".$validator->errors()->first());

                continue;
            }

            $payloads[] = [
                'line_number' => $lineNumber,
                'values' => $payload,
            ];
            $sortOrder++;
        }

        if ($payloads === []) {
            return;
        }

        $saved = 0;

        DB::transaction(function () use ($payloads, &$saved): void {
            if (! $this->usesIdColumn()) {
                PlanPoint::query()
                    ->where('plan_id', $this->plan->id)
                    ->delete();

                foreach (array_chunk($payloads, 500) as $chunk) {
                    PlanPoint::query()->insert(
                        collect($chunk)
                            ->map(fn (array $payload): array => $this->attributesForInsert($payload['values']))
                            ->all(),
                    );
                }

                $saved = count($payloads);

                return;
            }

            foreach ($payloads as $payload) {
                $values = $payload['values'];
                $id = $values['id'];
                unset($values['id'], $values['created_at']);

                if ($id !== null) {
                    $updated = PlanPoint::query()
                        ->where('plan_id', $this->plan->id)
                        ->whereKey($id)
                        ->update($values);

                    if ($updated === 0) {
                        $this->markSkipped("Line {$payload['line_number']}: plan point id {$id} was not found for this plan.");

                        continue;
                    }

                    $saved++;

                    continue;
                }

                PlanPoint::query()->create($values);
                $saved++;
            }
        });

        $this->imported = $saved;
    }

    public function endColumn(): string
    {
        return 'G';
    }

    public function limit(): int
    {
        return 5000;
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function result(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function isHeadingRow(array $values): bool
    {
        $first = $this->normalizeHeader((string) ($values[0] ?? ''));
        $second = $this->normalizeHeader((string) ($values[1] ?? ''));

        return in_array($first, ['id', 'خطة_التسميع', 'plan_name', 'name'], true)
            || in_array($second, ['خطة_التسميع', 'plan_name', 'name'], true)
            || in_array($second, ['النقاط', 'points'], true);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function headingHasIdColumn(array $values): bool
    {
        return $this->normalizeHeader((string) ($values[0] ?? '')) === 'id';
    }

    private function usesIdColumn(): bool
    {
        return $this->usesIdColumn ??= false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attributesForInsert(array $payload): array
    {
        unset($payload['id']);

        return $payload;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', '_', $value) ?? $value;

        return strtolower($value);
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function intOrNull(mixed $value): ?int
    {
        $raw = $this->nullIfEmpty($value);
        if ($raw === null) {
            return null;
        }

        return (int) $raw;
    }

    private function boolValue(mixed $value): bool
    {
        $raw = $this->nullIfEmpty($value);
        if ($raw === null) {
            return false;
        }

        return in_array($raw, ['1', 'true', 'yes', 'y', 'نعم', 'صح'], true);
    }

    private function markSkipped(string $message): void
    {
        $this->skipped++;
        $this->errors[] = $message;
    }
}
