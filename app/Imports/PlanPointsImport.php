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

    private ?bool $usesSortOrderColumn = null;

    private ?bool $usesWeightColumns = null;

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
                $this->usesSortOrderColumn ??= $this->headingHasSortOrderColumn($values, $this->usesIdColumn());
                $this->usesWeightColumns ??= $this->headingHasWeightColumns($values, $this->nameOffset());

                continue;
            }

            $nameOffset = $this->nameOffset();
            $usesWeightColumns = $this->usesWeightColumns();
            $certificateOffset = $usesWeightColumns ? $nameOffset + 4 : $nameOffset + 2;
            $surahOffset = $usesWeightColumns ? $nameOffset + 5 : $nameOffset + 3;
            $partOffset = $usesWeightColumns ? $nameOffset + 6 : $nameOffset + 4;
            $threePartsOffset = $usesWeightColumns ? $nameOffset + 7 : $nameOffset + 5;
            $importedWeight = $usesWeightColumns ? $this->floatOrNull($values[$nameOffset + 2] ?? null) : null;

            $payload = [
                'id' => $this->usesIdColumn() ? $this->intOrNull($values[0] ?? null) : null,
                'plan_id' => $this->plan->id,
                'sort_order' => $this->usesSortOrderColumn() ? ($this->intOrNull($values[$this->usesIdColumn() ? 1 : 0] ?? null) ?? $sortOrder) : $sortOrder,
                'name' => $this->nullIfEmpty($values[$nameOffset] ?? null),
                'points' => $this->intOrNull($values[$nameOffset + 1] ?? null),
                'weight' => $importedWeight ?? 1,
                'is_standalone' => $usesWeightColumns ? $this->boolValue($values[$nameOffset + 3] ?? null) : false,
                'plan_weight_rule_id' => null,
                'requires_certificate' => $this->boolValue($values[$certificateOffset] ?? null),
                'surah_name' => $this->nullIfEmpty($values[$surahOffset] ?? null),
                'part_name' => $this->nullIfEmpty($values[$partOffset] ?? null),
                'three_parts' => $this->nullIfEmpty($values[$threePartsOffset] ?? null),
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
                'weight' => ['required', 'numeric', 'min:0', 'max:99'],
                'is_standalone' => ['boolean'],
                'plan_weight_rule_id' => ['nullable', 'integer'],
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
        return 'J';
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

    private function usesSortOrderColumn(): bool
    {
        return $this->usesSortOrderColumn ??= false;
    }

    private function usesWeightColumns(): bool
    {
        return $this->usesWeightColumns ??= false;
    }

    private function nameOffset(): int
    {
        return ($this->usesIdColumn() ? 1 : 0) + ($this->usesSortOrderColumn() ? 1 : 0);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function headingHasSortOrderColumn(array $values, bool $usesIdColumn): bool
    {
        $offset = $usesIdColumn ? 1 : 0;
        $heading = $this->normalizeHeader((string) ($values[$offset] ?? ''));

        return in_array($heading, ['sort_order', 'الترتيب', 'ترتيب'], true);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function headingHasWeightColumns(array $values, int $nameOffset): bool
    {
        $heading = $this->normalizeHeader((string) ($values[$nameOffset + 2] ?? ''));

        return in_array($heading, ['weight', 'الوزن'], true);
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

    private function floatOrNull(mixed $value): ?float
    {
        $raw = $this->nullIfEmpty($value);
        if ($raw === null) {
            return null;
        }

        return (float) $raw;
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
