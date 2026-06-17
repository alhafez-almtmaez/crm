<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;

class PlanPointsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStrictNullComparison
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->map(static fn ($row): array => [
            $row->id,
            $row->name,
            $row->points,
            (bool) $row->requires_certificate ? 1 : null,
            $row->surah_name,
            $row->part_name,
            $row->three_parts,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'id',
            'خطة التسميع',
            'النقاط',
            'أخذ الشهادة',
            'اسم السورة',
            'اسم الجزء',
            'رقم الثلاث أجزاء',
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
