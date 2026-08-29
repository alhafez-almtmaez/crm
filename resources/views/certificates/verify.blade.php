@php
    $found = (bool) ($verification['found'] ?? false);
    $status = (string) ($verification['status'] ?? 'not_found');
    $statusClass = match ($status) {
        'valid' => 'status-valid',
        'revoked' => 'status-revoked',
        'replaced' => 'status-replaced',
        default => 'status-not-found',
    };
@endphp

<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $verification['headline'] }}</title>
    <style>
        @font-face {
            font-family: "Verification Arabic";
            src: url("{{ asset('fonts/certificate/NotoSansArabic-Regular.ttf') }}") format("truetype");
            font-weight: 400;
            font-display: swap;
        }

        @font-face {
            font-family: "Verification Arabic";
            src: url("{{ asset('fonts/certificate/NotoSansArabic-Bold.ttf') }}") format("truetype");
            font-weight: 700;
            font-display: swap;
        }

        :root {
            color-scheme: light;
            --ink: #17363d;
            --muted: #64767a;
            --line: #dce8e8;
            --surface: rgba(255, 255, 255, .97);
            --brand: #086c7c;
            --gold: #b67a20;
            --status: #8b4650;
            --status-soft: #fff1f2;
        }

        * { box-sizing: border-box; }
        html, body { min-height: 100%; margin: 0; }
        body {
            display: grid;
            min-height: 100vh;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at 10% 8%, rgba(182, 122, 32, .14), transparent 28rem),
                radial-gradient(circle at 90% 92%, rgba(8, 108, 124, .16), transparent 32rem),
                #f3f8f7;
            color: var(--ink);
            font-family: "Verification Arabic", Tahoma, Arial, sans-serif;
        }

        .status-valid { --status: #137557; --status-soft: #e9f8f1; }
        .status-revoked { --status: #b42332; --status-soft: #fff0f1; }
        .status-replaced { --status: #b45f06; --status-soft: #fff5e6; }
        .status-not-found { --status: #6c5c63; --status-soft: #f3f0f1; }

        .card {
            width: min(100%, 760px);
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 26px;
            background: var(--surface);
            box-shadow: 0 26px 70px rgba(27, 66, 71, .14);
        }

        .card::before {
            display: block;
            height: 5px;
            background: linear-gradient(90deg, var(--brand), var(--gold), var(--brand));
            content: "";
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px 30px;
            border-bottom: 1px solid var(--line);
        }

        .brand img {
            width: 70px;
            height: 70px;
            flex: 0 0 auto;
            object-fit: contain;
        }

        .brand p, .brand h2 { margin: 0; }
        .brand p { color: var(--gold); font-size: 13px; font-weight: 700; }
        .brand h2 { margin-top: 3px; font-size: clamp(18px, 4vw, 24px); }

        .content { padding: 32px 30px 30px; }
        .result {
            display: grid;
            grid-template-columns: 68px minmax(0, 1fr);
            align-items: center;
            gap: 18px;
        }

        .result-icon {
            display: grid;
            width: 68px;
            height: 68px;
            place-items: center;
            border-radius: 22px;
            background: var(--status-soft);
            color: var(--status);
            font-size: 34px;
            font-weight: 700;
        }

        .result h1 { margin: 0; color: var(--status); font-size: clamp(23px, 5vw, 34px); line-height: 1.4; }
        .result p { margin: 5px 0 0; color: var(--muted); }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 14px;
            padding: 7px 12px;
            border: 1px solid color-mix(in srgb, var(--status) 35%, transparent);
            border-radius: 999px;
            background: var(--status-soft);
            color: var(--status);
            font-size: 13px;
            font-weight: 700;
        }

        .badge::before { width: 7px; height: 7px; border-radius: 50%; background: currentColor; content: ""; }

        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 28px 0 0;
        }

        .detail {
            min-width: 0;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: #fbfdfd;
        }

        .detail-wide { grid-column: 1 / -1; }
        .detail dt { margin: 0 0 5px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .detail dd { margin: 0; overflow-wrap: anywhere; font-size: 16px; font-weight: 700; }
        .detail-number dd { direction: ltr; text-align: right; font-family: ui-monospace, monospace; }

        .notice {
            margin: 24px 0 0;
            padding: 14px 16px;
            border-radius: 14px;
            background: var(--status-soft);
            color: var(--status);
            line-height: 1.8;
        }

        @media (max-width: 600px) {
            body { padding: 12px; }
            .card { border-radius: 20px; }
            .brand, .content { padding: 21px 19px; }
            .brand img { width: 58px; height: 58px; }
            .result { grid-template-columns: 54px minmax(0, 1fr); gap: 13px; }
            .result-icon { width: 54px; height: 54px; border-radius: 17px; font-size: 28px; }
            .details { grid-template-columns: 1fr; }
            .detail-wide { grid-column: auto; }
        }
    </style>
</head>
<body class="{{ $statusClass }}">
    <main class="card">
        <header class="brand">
            <img src="{{ $verification['logo_url'] }}" alt="شعار {{ $verification['brand_name'] }}">
            <div>
                <p>التحقق الرسمي من الشهادات</p>
                <h2>{{ $verification['brand_name'] }}</h2>
            </div>
        </header>

        <section class="content">
            <div class="result">
                <span class="result-icon" aria-hidden="true">{{ $status === 'valid' ? '✓' : ($status === 'replaced' ? '↻' : '×') }}</span>
                <div>
                    <h1>{{ $verification['headline'] }}</h1>
                    <p>{{ $found ? 'تمت مطابقة بيانات الشهادة مع السجل الرسمي.' : 'لم نتمكن من مطابقة الرمز مع شهادة صادرة.' }}</p>
                    <span class="badge">الحالة: {{ $verification['status_label'] }}</span>
                </div>
            </div>

            @if ($found)
                <dl class="details">
                    <div class="detail detail-wide">
                        <dt>اسم الطالب/ة</dt>
                        <dd>{{ $verification['student_name'] }}</dd>
                    </div>
                    <div class="detail">
                        <dt>نوع الشهادة</dt>
                        <dd>{{ $verification['certificate_type'] }}</dd>
                    </div>
                    <div class="detail">
                        <dt>الإنجاز</dt>
                        <dd>{{ $verification['achievement'] }}</dd>
                    </div>
                    <div class="detail detail-number">
                        <dt>رقم الشهادة</dt>
                        <dd>{{ $verification['certificate_number'] }}</dd>
                    </div>
                    <div class="detail">
                        <dt>تاريخ الإصدار</dt>
                        <dd>{{ $verification['issued_at'] }}</dd>
                    </div>
                </dl>

                @if ($status !== 'valid')
                    <p class="notice">هذه الصفحة تعرض الحالة الحالية للشهادة. راجع الجهة المصدرة عند الحاجة إلى مزيد من المعلومات.</p>
                @endif
            @else
                <p class="notice">تأكد من مسح رمز QR الموجود على الشهادة الأصلية، أو تواصل مع الجهة المصدرة للتحقق.</p>
            @endif
        </section>
    </main>
</body>
</html>
