<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;

class StudentsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStrictNullComparison
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->map(static fn ($row): array => [
            $row->id,
            $row->full_name,
            $row->first_name,
            $row->second_name,
            $row->middle_name,
            $row->last_name,
            $row->parent_phone_number,
            $row->phone_number,
            $row->email,
            $row->id_number,
            $row->date_of_birth,
            $row->center_name,
            $row->center_id,
            $row->group_name,
            $row->group_id,
            $row->plan_name,
            $row->plan_type_id,
            $row->plan_point_name,
            $row->plan_point_id,
            $row->points_balance,
            $row->max_daily_weight,
            is_array($row->daily_weight_limits ?? null)
                ? json_encode($row->daily_weight_limits)
                : ($row->daily_weight_limits ?? null),
            $row->admin_name,
            $row->admin_id,
            $row->is_active,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'id',
            'full_name',
            'first_name',
            'second_name',
            'middle_name',
            'last_name',
            'parent_phone_number',
            'phone_number',
            'email',
            'id_number',
            'date_of_birth',
            'center_name',
            'center_id',
            'group_name',
            'group_id',
            'plan_name',
            'plan_type_id',
            'plan_point_name',
            'plan_point_id',
            'points_balance',
            'max_daily_weight',
            'daily_weight_limits',
            'admin_name',
            'admin_id',
            'is_active',
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => static function (AfterSheet $event): void {
                $event->sheet->getDelegate()->setRightToLeft(true);
            },
        ];
    }
}
