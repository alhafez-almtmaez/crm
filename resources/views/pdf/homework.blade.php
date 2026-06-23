<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        @if (! empty($pdf['fonts']['naskh_regular']))
            @font-face {
                font-family: "HomeworkNaskh";
                src: url("{{ $pdf['fonts']['naskh_regular'] }}") format("truetype");
                font-weight: 400;
                font-style: normal;
            }
        @endif

        @if (! empty($pdf['fonts']['naskh_bold']))
            @font-face {
                font-family: "HomeworkNaskh";
                src: url("{{ $pdf['fonts']['naskh_bold'] }}") format("truetype");
                font-weight: 700;
                font-style: normal;
            }
        @endif

        @if (! empty($pdf['fonts']['kufi_regular']))
            @font-face {
                font-family: "HomeworkKufi";
                src: url("{{ $pdf['fonts']['kufi_regular'] }}") format("truetype");
                font-weight: 400;
                font-style: normal;
            }
        @endif

        @if (! empty($pdf['fonts']['kufi_bold']))
            @font-face {
                font-family: "HomeworkKufi";
                src: url("{{ $pdf['fonts']['kufi_bold'] }}") format("truetype");
                font-weight: 700;
                font-style: normal;
            }
        @endif

        @page {
            size: A4 portrait;
            margin: 9mm;
        }

        * {
            box-sizing: border-box;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            color: #172033;
            background: #ffffff;
            font-family: "HomeworkNaskh", "Noto Naskh Arabic", "DejaVu Sans", Tahoma, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        .sheet {
            min-height: 279mm;
            border: 1.6px solid #172033;
            padding: 13mm 8.5mm 10mm;
            position: relative;
        }

        .sheet::before {
            content: "";
            position: absolute;
            inset: 2mm;
            border: 0.7px solid #d6dde7;
            pointer-events: none;
        }

        .top {
            display: grid;
            grid-template-columns: 72px 1fr 240px;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            direction: ltr;
            position: relative;
            z-index: 1;
        }

        .logo {
            width: 58px;
            max-height: 68px;
            object-fit: contain;
        }

        .title {
            margin: 0;
            color: #8a1114;
            direction: rtl;
            font-family: "HomeworkKufi", "HomeworkNaskh", Tahoma, Arial, sans-serif;
            font-size: 23px;
            font-weight: 900;
            line-height: 1.4;
            text-align: center;
        }

        .date-box {
            color: #172033;
            direction: rtl;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.65;
            text-align: start;
        }

        .date-box div + div {
            margin-top: 3px;
        }

        .date-label {
            color: #334155;
            font-family: "HomeworkKufi", "HomeworkNaskh", Tahoma, Arial, sans-serif;
            font-size: 12px;
            font-weight: 800;
        }

        .band {
            border: 1.2px solid #7890a4;
            border-bottom: 0;
            background: #dff2f8;
            color: #172033;
            padding: 9px 10px 10px;
            font-family: "HomeworkKufi", "HomeworkNaskh", Tahoma, Arial, sans-serif;
            font-size: 18px;
            font-weight: 900;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1.2px solid #4b5f73;
            position: relative;
            z-index: 1;
        }

        th,
        td {
            border: 1px solid #4b5f73;
            padding: 2px 4px;
            text-align: center;
            vertical-align: middle;
            overflow-wrap: anywhere;
            white-space: pre-line;
        }

        .task-text {
            direction: rtl;
            unicode-bidi: plaintext;
            display: inline-block;
            line-height: 1.18;
        }

        .ltr-range {
            display: inline-block;
            direction: ltr;
            unicode-bidi: isolate;
        }

        thead {
            display: table-header-group;
        }

        thead th {
            font-family: "HomeworkKufi", "HomeworkNaskh", Tahoma, Arial, sans-serif;
            font-size: 14px;
            font-weight: 900;
        }

        .student-header {
            width: 24%;
            color: #ffffff;
            background: #c5161d;
            border-color: #912025;
        }

        .homework-header {
            color: #172033;
            background: #fff5f7;
        }

        .spacer-header {
            height: 8px;
            padding: 0;
            background: #fff9fb;
        }

        tbody td {
            height: 22px;
            font-size: 12.3px;
            font-weight: 800;
        }

        tbody tr {
            break-inside: avoid;
        }

        tbody tr:nth-child(even) td {
            background: #fcfdff;
        }

        .student-name {
            color: #172033;
            font-family: "HomeworkKufi", "HomeworkNaskh", Tahoma, Arial, sans-serif;
            font-size: 12px;
            font-weight: 900;
            text-align: start;
            padding-inline-start: 6px;
            white-space: nowrap;
        }

        .empty {
            color: #9ca3af;
            font-weight: 500;
        }
    </style>
</head>
@php
    $formatTask = static function (string $cell): string {
        $escaped = e($cell);

        return preg_replace_callback(
            '/\[[^\]]+\]/u',
            static fn (array $match): string => '<span class="ltr-range">'.e($match[0]).'</span>',
            $escaped,
        ) ?? $escaped;
    };
@endphp
<body>
    <main class="sheet">
        <section class="top">
            <div>
                @if (! empty($pdf['logo_data_uri']))
                    <img class="logo" src="{{ $pdf['logo_data_uri'] }}" alt="">
                @endif
            </div>

            <h1 class="title">{{ $pdf['title'] }}</h1>

            <div class="date-box">
                <div><span class="date-label">{{ __('homeworks.pdf_day') }}:</span> {{ $homework['day_name'] }}</div>
                <div><span class="date-label">{{ __('homeworks.pdf_date') }}:</span> <span class="ltr-range">{{ $homework['date_numeric'] }}</span></div>
                @if (! empty($homework['next_homework_date_numeric']))
                    <div><span class="date-label">{{ __('homeworks.pdf_next_homework_date') }}:</span> <span class="ltr-range">{{ $homework['next_homework_date_numeric'] }}</span></div>
                @endif
            </div>
        </section>

        <div class="band">{{ $pdf['title'] }}</div>

        <table>
            <thead>
                <tr>
                    <th class="student-header" rowspan="2">{{ __('homeworks.pdf_student') }}</th>
                    <th class="homework-header" colspan="{{ count($pdf['assignment_columns']) }}">
                        {{ __('homeworks.pdf_required_homework') }}
                    </th>
                </tr>
                <tr>
                    @foreach ($pdf['assignment_columns'] as $column)
                        <th class="homework-header spacer-header"></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td class="student-name">{{ $student['pdf_name'] }}</td>
                        @foreach ($student['pdf_homework_cells'] as $cell)
                            <td>
                                @if ($cell !== '')
                                    <span class="task-text">{!! $formatTask($cell) !!}</span>
                                @else
                                    <span class="empty">&nbsp;</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($pdf['assignment_columns']) + 1 }}">{{ __('homeworks.pdf_none') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
