@php
    $previewMode = (bool) ($certificate['design_preview_mode'] ?? false);
    $showCenterIdentity = (bool) ($certificate['show_center_manager_signature'] ?? true);
    $previewCatalog = is_array($certificate['preview_catalog'] ?? null)
        ? $certificate['preview_catalog']
        : [];
    $previewSamples = is_array($certificate['preview_samples'] ?? null)
        ? $certificate['preview_samples']
        : [];
    $contentTemplate = is_array($certificate['content_template'] ?? null)
        ? $certificate['content_template']
        : null;
    $contentTemplateSegments = is_array($contentTemplate['rendered_segments'] ?? null)
        ? $contentTemplate['rendered_segments']
        : [];
    $usesContentTemplate = $contentTemplate !== null
        && collect(['title', 'quote_first', 'quote_second', 'intro', 'student_line', 'achievement_line', 'closing'])
            ->every(static fn (string $key): bool => is_array($contentTemplateSegments[$key] ?? null));
    $contentTemplateRendered = is_array($contentTemplate['rendered_sections'] ?? null)
        ? $contentTemplate['rendered_sections']
        : [];
    $visualLength = static function (mixed $value): int {
        $text = is_scalar($value) ? (string) $value : '';
        $text = preg_replace('/\p{Mn}+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        return mb_strlen($text);
    };
    $studentName = (string) ($certificate['student_name'] ?? '');
    $studentNameLength = $usesContentTemplate
        ? $visualLength($contentTemplateRendered['student_line'] ?? '')
        : mb_strlen($studentName);
    $studentNameClass = $studentNameLength > 55
        ? 'certificate__student--extra-long'
        : ($studentNameLength > 36
            ? 'certificate__student--very-long'
            : ($studentNameLength > 25 ? 'certificate__student--long' : ''));

    $achievementName = (string) ($certificate['achievement_name'] ?? '');
    $achievementNameLength = $usesContentTemplate
        ? $visualLength($contentTemplateRendered['achievement_line'] ?? '')
        : mb_strlen($achievementName);
    $achievementNameClass = $achievementNameLength > ($usesContentTemplate ? 155 : 32)
        ? 'certificate__achievement--very-long'
        : ($achievementNameLength > ($usesContentTemplate ? 105 : 20)
            ? 'certificate__achievement--long'
            : '');
    $titleLength = $visualLength($contentTemplateRendered['title'] ?? $certificate['title'] ?? '');
    $titleClass = $titleLength > 45
        ? 'certificate__title--very-long'
        : ($titleLength > 32 ? 'certificate__title--long' : '');
    $quoteLength = $visualLength($contentTemplateRendered['quote_first'] ?? $certificate['quote_first'] ?? '')
        + $visualLength($contentTemplateRendered['quote_second'] ?? $certificate['quote_second'] ?? '');
    $quoteClass = $quoteLength > 105
        ? 'certificate__quote--very-long'
        : ($quoteLength > 75 ? 'certificate__quote--long' : '');
    $introLength = $visualLength($contentTemplateRendered['intro'] ?? '');
    $introClass = $introLength > 170
        ? 'certificate__intro--very-long'
        : ($introLength > 115 ? 'certificate__intro--long' : '');
    $closingLength = $visualLength($contentTemplateRendered['closing'] ?? $certificate['closing_text'] ?? '');
    $closingClass = $closingLength > 160
        ? 'certificate__closing--very-long'
        : ($closingLength > 105 ? 'certificate__closing--long' : '');

    $design = is_array($certificate['design'] ?? null) ? $certificate['design'] : [];
    $designStyles = [];
    $colorVariables = [
        'heading_color' => '--certificate-heading-color',
        'student_name_color' => '--certificate-student-name-color',
        'content_color' => '--certificate-content-color',
        'accent_color' => '--certificate-accent-color',
    ];

    foreach ($colorVariables as $designKey => $cssVariable) {
        $color = $design[$designKey] ?? null;

        if (is_string($color) && preg_match('/\A#[0-9A-Fa-f]{6}\z/', $color) === 1) {
            $designStyles[] = "{$cssVariable}: {$color}";
        }
    }

    $qrForegroundColor = $certificate['qr_foreground_color'] ?? null;
    if (is_string($qrForegroundColor)
        && preg_match('/\A#[0-9A-Fa-f]{6}\z/', $qrForegroundColor) === 1) {
        $designStyles[] = "--certificate-qr-color: {$qrForegroundColor}";
    }

    $fontVariables = [
        'body_font_family' => '--certificate-body-font',
        'display_font_family' => '--certificate-display-font',
    ];

    foreach ($fontVariables as $designKey => $cssVariable) {
        $fontFamily = $design[$designKey] ?? null;

        if (is_string($fontFamily)
            && preg_match('/\A[A-Za-z0-9\s,"\'\-]+\z/', $fontFamily) === 1
            && trim($fontFamily) !== '') {
            $designStyles[] = "{$cssVariable}: ".trim($fontFamily);
        }
    }

    $designStyle = implode('; ', $designStyles);
    $qrCodeDataUri = trim((string) ($certificate['qr_code_data_uri'] ?? ''));
    $verificationUrl = trim((string) ($certificate['verification_url'] ?? ''));
    $hasVerificationQr = ! $previewMode && $qrCodeDataUri !== '' && $verificationUrl !== '';
    $hasVerificationPreview = (bool) ($certificate['verification_preview'] ?? false)
        && ! $hasVerificationQr;
    $showsVerificationBlock = $hasVerificationQr || $hasVerificationPreview;
@endphp

<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $certificate['page_title'] }}</title>
    @foreach ($certificate['font_preload_urls'] as $fontUrl)
        <link rel="preload" href="{{ $fontUrl }}" as="font" type="font/ttf" crossorigin>
    @endforeach
    <link rel="stylesheet" href="{{ $certificate['stylesheet_url'] }}">
</head>
<body @class(['certificate-preview-body' => $previewMode])>
    <main @class(['certificate-page', 'certificate-page--preview' => $previewMode]) @if ($designStyle !== '') style="{{ $designStyle }}" @endif>
        @if (! $certificate['pdf_mode'] && ! $previewMode)
            <div class="certificate-toolbar" aria-label="{{ $certificate['labels']['tools'] }}">
                <a class="certificate-toolbar__link certificate-toolbar__link--secondary" href="{{ $certificate['back_url'] }}">
                    {{ $certificate['labels']['back'] }}
                </a>
                <a class="certificate-toolbar__link" href="{{ $certificate['pdf_url'] }}">
                    {{ $certificate['labels']['download_pdf'] }}
                </a>
                <button type="button" onclick="window.print()">{{ $certificate['labels']['print'] }}</button>
            </div>
        @endif

        <article @class([
            'certificate',
            'certificate--project-only' => ! $showCenterIdentity,
            'certificate--with-verification' => $showsVerificationBlock,
        ]) data-certificate-preview-certificate aria-label="{{ $certificate['title'] }}">
            @if ($certificate['images']['frame'] !== '')
                <img class="certificate__frame"
                     data-certificate-preview-frame
                     src="{{ $certificate['images']['frame'] }}"
                     alt=""
                     aria-hidden="true">
            @endif

            @if (($showCenterIdentity || $previewMode) && $certificate['images']['left_logo'] !== '')
                <img class="certificate__logo certificate__logo--left"
                     data-certificate-preview-center-identity
                     @if ($previewMode && ! $showCenterIdentity) hidden @endif
                     src="{{ $certificate['images']['left_logo'] }}"
                     alt="{{ $certificate['labels']['left_logo'] }}">
            @endif

            @if ($certificate['images']['right_logo'] !== '')
                <img class="certificate__logo certificate__logo--right"
                     src="{{ $certificate['images']['right_logo'] }}"
                     alt="{{ $certificate['labels']['right_logo'] }}">
            @endif

            @if (($previewMode || ! $showCenterIdentity) && $certificate['images']['right_logo'] !== '')
                <img class="certificate__logo certificate__logo--right certificate__logo--project-left"
                     data-certificate-preview-project-left
                     @if ($previewMode && $showCenterIdentity) hidden @endif
                     src="{{ $certificate['images']['right_logo'] }}"
                     alt=""
                     aria-hidden="true">
            @endif

            <h1 @class(['certificate__title', $titleClass => $titleClass !== '']) data-certificate-content-section="title" style="white-space: pre-line">@if ($usesContentTemplate)@include('certificates.partials.template-segments', ['segments' => $contentTemplateSegments['title']])@else{{ $certificate['title'] }}@endif</h1>

            <div @class(['certificate__quote', $quoteClass => $quoteClass !== '']) aria-label="{{ $certificate['labels']['poem'] }}">
                <span data-certificate-content-section="quote_first" style="white-space: pre-line">@if ($usesContentTemplate)@include('certificates.partials.template-segments', ['segments' => $contentTemplateSegments['quote_first']])@else{{ $certificate['quote_first'] }}@endif</span>
                <span class="certificate__quote-ornament" aria-hidden="true">✧✦✧</span>
                <span data-certificate-content-section="quote_second" style="white-space: pre-line">@if ($usesContentTemplate)@include('certificates.partials.template-segments', ['segments' => $contentTemplateSegments['quote_second']])@else{{ $certificate['quote_second'] }}@endif</span>
            </div>

            @if ($usesContentTemplate)
                <p @class(['certificate__intro', $introClass => $introClass !== '']) data-certificate-content-section="intro" style="white-space: pre-line">
                    @include('certificates.partials.template-segments', ['segments' => $contentTemplateSegments['intro']])
                </p>

                <p @class(['certificate__student', $studentNameClass => $studentNameClass !== '']) data-certificate-content-section="student_line">
                    @include('certificates.partials.template-segments', ['segments' => $contentTemplateSegments['student_line']])
                </p>

                <p @class(['certificate__achievement', $achievementNameClass => $achievementNameClass !== '']) data-certificate-content-section="achievement_line" style="white-space: pre-line">
                    @include('certificates.partials.template-segments', ['segments' => $contentTemplateSegments['achievement_line']])
                </p>

                <p @class(['certificate__closing', $closingClass => $closingClass !== '']) data-certificate-content-section="closing" style="white-space: pre-line">
                    @include('certificates.partials.template-segments', ['segments' => $contentTemplateSegments['closing']])
                </p>
            @else
                <p class="certificate__intro" data-certificate-content-section="intro">
                    <span data-certificate-preview-intro-before-project>{{ $certificate['intro_before_project'] }}</span>
                    <strong data-certificate-preview-center-name>{{ $certificate['center_name'] }}</strong>
                    <span data-certificate-preview-intro-after-center>{{ $certificate['intro_after_center'] }}</span>
                </p>

                <p class="certificate__student {{ $studentNameClass }}" data-certificate-content-section="student_line">
                    <span class="ornament">﴿</span>
                    <strong data-certificate-preview-student>{{ $studentName }}</strong>
                    <span class="ornament">﴾</span>
                </p>

                <p class="certificate__achievement {{ $achievementNameClass }}" data-certificate-content-section="achievement_line">
                    <span data-certificate-preview-achievement-intro>{{ $certificate['achievement_intro'] }}</span>
                    <span data-certificate-preview-achievement-label>{{ $certificate['achievement_label'] }}</span>
                    <span class="ornament">﴿</span>
                    <strong data-certificate-preview-achievement-name>{{ $achievementName }}</strong>
                    <span class="ornament">﴾</span>
                    <span data-certificate-preview-achievement-suffix>{{ $certificate['achievement_suffix'] }}</span>
                </p>

                <p class="certificate__closing" data-certificate-content-section="closing" data-certificate-preview-closing>{{ $certificate['closing_text'] }}</p>
            @endif

            @if ($showCenterIdentity || $previewMode)
                <section class="certificate__signing certificate__signing--center"
                         data-certificate-preview-center-identity
                         @if ($previewMode && ! $showCenterIdentity) hidden @endif
                         aria-label="{{ $certificate['labels']['center_signature'] }}">
                    <h2>{{ $certificate['center_manager_title'] }}</h2>
                    @if ($certificate['images']['center_stamp'] !== '')
                        <img class="signing__stamp" src="{{ $certificate['images']['center_stamp'] }}" alt="{{ $certificate['labels']['center_stamp'] }}">
                    @endif
                    @if ($certificate['images']['center_signature'] !== '')
                        <img class="signing__signature" src="{{ $certificate['images']['center_signature'] }}" alt="{{ $certificate['labels']['center_signature'] }}">
                    @endif
                </section>
            @endif

            <section @class([
                'certificate__signing',
                'certificate__signing--project',
                'certificate__signing--project-solo' => ! $showCenterIdentity,
            ]) data-certificate-preview-project-signing aria-label="{{ $certificate['labels']['project_signature'] }}">
                <h2>{{ $certificate['project_manager_title'] }}</h2>
                @if ($certificate['images']['project_stamp'] !== '')
                    <img class="signing__stamp" src="{{ $certificate['images']['project_stamp'] }}" alt="{{ $certificate['labels']['project_stamp'] }}">
                @endif
                @if ($certificate['images']['project_signature'] !== '')
                    <img class="signing__signature" src="{{ $certificate['images']['project_signature'] }}" alt="{{ $certificate['labels']['project_signature'] }}">
                @endif
            </section>

            <section class="certificate__dates" aria-label="{{ $certificate['labels']['achievement_date'] }}">
                <h2>{{ $certificate['date_title'] }}</h2>
                <p>{{ $certificate['labels']['hijri'] }}: <bdi>{{ $certificate['hijri_date'] }}</bdi></p>
                <p>{{ $certificate['labels']['gregorian'] }}: <bdi>{{ $certificate['gregorian_date'] }}</bdi></p>
            </section>

            @if ($hasVerificationQr)
                <a class="certificate__verification"
                   href="{{ $verificationUrl }}"
                   aria-label="{{ $certificate['labels']['verify_certificate'] }}">
                    <img class="certificate__verification-qr"
                         src="{{ $qrCodeDataUri }}"
                         alt=""
                         aria-hidden="true">
                    <span class="certificate__verification-label">{{ $certificate['labels']['verify_certificate'] }}</span>
                    <span class="certificate__verification-number">
                        {{ $certificate['labels']['certificate_number'] }}:
                        <bdi>{{ $certificate['certificate_number'] }}</bdi>
                    </span>
                </a>
            @elseif ($hasVerificationPreview)
                <div class="certificate__verification certificate__verification--preview"
                     aria-label="رمز تحقق تجريبي للمعاينة فقط">
                    <svg class="certificate__verification-qr"
                         viewBox="0 0 29 29"
                         role="img"
                         aria-label="شكل QR تجريبي غير قابل للمسح"
                         shape-rendering="crispEdges">
                        <rect width="29" height="29" fill="#fff"/>
                        <g fill="currentColor">
                            <path d="M2 2h7v7H2zM20 2h7v7h-7zM2 20h7v7H2z"/>
                            <path d="M4 4h3v3H4zM22 4h3v3h-3zM4 22h3v3H4z"/>
                            <path d="M11 2h2v2h-2zM15 2h1v1h-1zM17 3h1v3h-1zM11 6h1v2h-1zM14 5h2v1h-2zM12 10h1v2h-1zM15 9h2v2h-2zM19 10h2v1h-2zM23 11h3v2h-3zM3 11h2v1H3zM7 10h2v3H7zM10 14h2v2h-2zM13 13h1v4h-1zM16 13h3v2h-3zM21 14h2v3h-2zM25 15h2v2h-2zM3 14h2v3H3zM6 16h2v2H6zM9 18h2v1H9zM12 19h2v2h-2zM15 17h2v2h-2zM18 18h1v3h-1zM20 20h2v2h-2zM23 19h3v1h-3zM25 22h2v3h-2zM11 23h2v3h-2zM15 22h1v2h-1zM18 24h3v2h-3z"/>
                        </g>
                        <g fill="#fff">
                            <path d="M3 3h5v5H3zM21 3h5v5h-5zM3 21h5v5H3z"/>
                        </g>
                        <g fill="currentColor">
                            <path d="M4 4h3v3H4zM22 4h3v3h-3zM4 22h3v3H4z"/>
                        </g>
                    </svg>
                    <span class="certificate__verification-label">{{ $certificate['labels']['verify_certificate'] }}</span>
                    <span class="certificate__verification-number">
                        {{ $certificate['labels']['certificate_number'] }}:
                        <bdi>{{ $certificate['certificate_number'] }}</bdi>
                    </span>
                </div>
            @endif

        </article>
    </main>
    @if ($previewMode)
        <script>
            (() => {
                'use strict';

                const messageType = 'certificate-design-preview:update';
                const readyType = 'certificate-design-preview:ready';
                const catalog = {{ Illuminate\Support\Js::from($previewCatalog) }};
                const samples = {{ Illuminate\Support\Js::from($previewSamples) }};
                const themes = catalog && typeof catalog.themes === 'object' ? catalog.themes : {};
                const fonts = catalog && typeof catalog.fonts === 'object' ? catalog.fonts : {};
                const centers = catalog && typeof catalog.centers === 'object' ? catalog.centers : {};
                const achievements = catalog && typeof catalog.achievements === 'object'
                    ? catalog.achievements
                    : {};
                const colorVariables = {
                    heading_color: '--certificate-heading-color',
                    student_name_color: '--certificate-student-name-color',
                    content_color: '--certificate-content-color',
                    accent_color: '--certificate-accent-color',
                };
                const qrFallbackColor = '#09232A';
                const normalizeHexColor = (value) => typeof value === 'string'
                    && /^#[0-9A-Fa-f]{6}$/.test(value)
                        ? value.toUpperCase()
                        : null;
                const relativeLuminance = (hex) => {
                    const channels = [1, 3, 5].map((offset) => {
                        const channel = Number.parseInt(hex.slice(offset, offset + 2), 16) / 255;

                        return channel <= 0.04045
                            ? channel / 12.92
                            : ((channel + 0.055) / 1.055) ** 2.4;
                    });

                    return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
                };
                const contrastAgainstWhite = (hex) => 1.05 / (relativeLuminance(hex) + 0.05);
                const qrColorFromAccent = (value) => {
                    const accent = normalizeHexColor(value);
                    if (!accent) return qrFallbackColor;

                    const channels = [1, 3, 5].map(
                        (offset) => Number.parseInt(accent.slice(offset, offset + 2), 16),
                    );

                    for (let percentage = 100; percentage >= 0; percentage -= 5) {
                        const candidate = `#${channels.map((channel) => Math.round(
                            (channel * percentage) / 100,
                        ).toString(16).padStart(2, '0')).join('').toUpperCase()}`;

                        if (contrastAgainstWhite(candidate) >= 7) return candidate;
                    }

                    return qrFallbackColor;
                };
                const root = document.querySelector('.certificate-page');
                const certificateElement = document.querySelector('[data-certificate-preview-certificate]');
                const frame = document.querySelector('[data-certificate-preview-frame]');
                const centerName = document.querySelector('[data-certificate-preview-center-name]');
                const centerIdentityElements = document.querySelectorAll('[data-certificate-preview-center-identity]');
                const projectLeftLogo = document.querySelector('[data-certificate-preview-project-left]');
                const projectSigning = document.querySelector('[data-certificate-preview-project-signing]');
                const student = document.querySelector('[data-certificate-preview-student]');
                const achievementLabel = document.querySelector('[data-certificate-preview-achievement-label]');
                const achievementName = document.querySelector('[data-certificate-preview-achievement-name]');
                const contentSectionKeys = [
                    'title',
                    'quote_first',
                    'quote_second',
                    'intro',
                    'student_line',
                    'achievement_line',
                    'closing',
                ];
                const contentSectionElements = Object.fromEntries(contentSectionKeys.map((key) => [
                    key,
                    document.querySelector(`[data-certificate-content-section="${key}"]`) ?? ({
                        intro: document.querySelector('.certificate__intro'),
                        student_line: document.querySelector('.certificate__student'),
                        achievement_line: document.querySelector('.certificate__achievement'),
                        closing: document.querySelector('.certificate__closing'),
                    })[key] ?? null,
                ]));
                const allowedTemplateVariables = new Set([
                    'student_name',
                    'center_name',
                    'achievement_label',
                    'achievement_name',
                    'certificate_number',
                    'plan_name',
                    'plan_point_name',
                    'hijri_date',
                    'gregorian_date',
                ]);
                const wordingElements = {
                    intro_before_project: document.querySelector('[data-certificate-preview-intro-before-project]'),
                    intro_after_center: document.querySelector('[data-certificate-preview-intro-after-center]'),
                    achievement_intro: document.querySelector('[data-certificate-preview-achievement-intro]'),
                    achievement_suffix: document.querySelector('[data-certificate-preview-achievement-suffix]'),
                    closing_text: document.querySelector('[data-certificate-preview-closing]'),
                };
                const defaultWording = Object.fromEntries(Object.entries(wordingElements).map(
                    ([key, element]) => [key, element?.textContent ?? ''],
                ));
                const owns = (object, key) => Object.prototype.hasOwnProperty.call(object, key);
                const safeSample = (group, key) => {
                    const values = samples && typeof samples[group] === 'object' ? samples[group] : {};
                    return owns(values, key) && typeof values[key] === 'string' ? values[key] : null;
                };
                const emphasizedTemplateVariables = new Set([
                    'student_name',
                    'center_name',
                    'achievement_name',
                ]);
                const renderPlainTemplate = (element, source, values) => {
                    const fragment = document.createDocumentFragment();
                    const pattern = /\{\{\s*([a-z_]+)\s*\}\}/gu;
                    let cursor = 0;
                    let match;

                    while ((match = pattern.exec(source)) !== null) {
                        if (match.index > cursor) {
                            fragment.append(document.createTextNode(source.slice(cursor, match.index)));
                        }

                        const key = match[1];
                        if (allowedTemplateVariables.has(key) && typeof values[key] === 'string') {
                            const variable = document.createElement(
                                emphasizedTemplateVariables.has(key) ? 'strong' : 'span',
                            );
                            variable.dataset.certificateTemplateVariable = key;
                            variable.textContent = values[key];
                            fragment.append(variable);
                        } else {
                            fragment.append(document.createTextNode(match[0]));
                        }
                        cursor = pattern.lastIndex;
                    }

                    if (cursor < source.length) {
                        fragment.append(document.createTextNode(source.slice(cursor)));
                    }

                    element.replaceChildren(fragment);
                };
                const setLengthClass = (element, classes, value, thresholds) => {
                    const container = element?.parentElement;
                    if (!container) return;

                    container.classList.remove(...classes);
                    const length = Array.from(value).length;
                    const match = thresholds.find(({ minimum }) => length > minimum);
                    if (match) container.classList.add(match.className);
                };
                const visualLength = (value) => Array.from(String(value ?? '')
                    .normalize('NFD')
                    .replace(/\p{Mn}+/gu, '')
                    .trim()
                    .replace(/\s+/gu, ' ')).length;
                const setSectionLengthClass = (element, classes, thresholds, length = null) => {
                    if (!element) return;

                    element.classList.remove(...classes);
                    const resolvedLength = length ?? visualLength(element.textContent);
                    const match = thresholds.find(({ minimum }) => resolvedLength > minimum);
                    if (match) element.classList.add(match.className);
                };
                window.addEventListener('message', (event) => {
                    if (event.origin !== window.location.origin
                        || event.source !== window.parent
                        || event.data?.type !== messageType
                        || !root) {
                        return;
                    }

                    const data = event.data;
                    const themeKey = typeof data.theme === 'string' ? data.theme : '';
                    const fontKey = typeof data.font === 'string' ? data.font : '';
                    const centerId = Number(data.center_id);
                    const centerKey = Number.isInteger(centerId) && centerId > 0
                        ? String(centerId)
                        : '';
                    const theme = owns(themes, themeKey) && typeof themes[themeKey] === 'object'
                        ? themes[themeKey]
                        : null;
                    const font = owns(fonts, fontKey) && typeof fonts[fontKey] === 'object'
                        ? fonts[fontKey]
                        : null;
                    const center = centerKey !== ''
                        && owns(centers, centerKey)
                        && typeof centers[centerKey] === 'object'
                            ? centers[centerKey]
                            : null;

                    if (frame && typeof theme?.frame_url === 'string' && theme.frame_url !== '') {
                        frame.src = theme.frame_url;
                    }

                    if (font) {
                        if (typeof font.body_family === 'string' && font.body_family !== '') {
                            root.style.setProperty('--certificate-body-font', font.body_family);
                        }
                        if (typeof font.display_family === 'string' && font.display_family !== '') {
                            root.style.setProperty('--certificate-display-font', font.display_family);
                        }
                    }

                    Object.entries(colorVariables).forEach(([key, cssVariable]) => {
                        const color = data[key];
                        if (typeof color === 'string' && /^#[0-9A-Fa-f]{6}$/.test(color)) {
                            root.style.setProperty(cssVariable, color.toUpperCase());
                        }
                    });
                    root.style.setProperty('--certificate-qr-color', qrColorFromAccent(data.accent_color));

                    const requestedGender = ['male', 'female'].includes(data.gender)
                        ? data.gender
                        : '';
                    const gender = center && ['male', 'female'].includes(center.student_gender)
                        ? center.student_gender
                        : requestedGender;
                    const hasCenter = center !== null;
                    const showCenterIdentity = hasCenter
                        && center.show_center_manager_signature === true;

                    if (centerName) {
                        centerName.textContent = hasCenter
                            && typeof center.center_name === 'string'
                            && center.center_name.trim() !== ''
                                ? center.center_name
                                : '—';
                    }

                    certificateElement?.classList.toggle('certificate--project-only', !showCenterIdentity);
                    projectSigning?.classList.toggle('certificate__signing--project-solo', !showCenterIdentity);
                    centerIdentityElements.forEach((element) => {
                        element.hidden = !showCenterIdentity;
                    });
                    if (projectLeftLogo) {
                        projectLeftLogo.hidden = showCenterIdentity;
                    }

                    const achievementType = typeof data.achievement_type === 'string'
                        ? data.achievement_type
                        : '';
                    const studentName = safeSample('student_names', gender);
                    const label = safeSample('achievement_labels', achievementType);
                    const planPointId = Number(data.plan_point_id);
                    const planPointKey = Number.isInteger(planPointId) && planPointId > 0
                        ? String(planPointId)
                        : '';
                    const achievement = planPointKey !== ''
                        && owns(achievements, planPointKey)
                        && typeof achievements[planPointKey] === 'object'
                        && achievements[planPointKey].achievement_type === achievementType
                            ? achievements[planPointKey]
                            : null;
                    const resolvedAchievementName = typeof achievement?.achievement_name === 'string'
                        && achievement.achievement_name.trim() !== ''
                            ? achievement.achievement_name
                            : '—';
                    const wordingByGender = samples && typeof samples.wording === 'object'
                        ? samples.wording
                        : {};
                    const wording = owns(wordingByGender, gender) && typeof wordingByGender[gender] === 'object'
                        ? wordingByGender[gender]
                        : {};

                    Object.entries(wordingElements).forEach(([key, element]) => {
                        if (!element) return;

                        element.textContent = owns(wording, key) && typeof wording[key] === 'string'
                            ? wording[key]
                            : defaultWording[key];
                    });

                    if (student && studentName !== null) {
                        student.textContent = studentName;
                        setLengthClass(
                            student,
                            ['certificate__student--long', 'certificate__student--very-long', 'certificate__student--extra-long'],
                            studentName,
                            [
                                { minimum: 55, className: 'certificate__student--extra-long' },
                                { minimum: 36, className: 'certificate__student--very-long' },
                                { minimum: 25, className: 'certificate__student--long' },
                            ],
                        );
                    }
                    if (achievementLabel && label !== null) achievementLabel.textContent = label;
                    if (achievementName) {
                        achievementName.textContent = resolvedAchievementName;
                        setLengthClass(
                            achievementName,
                            ['certificate__achievement--long', 'certificate__achievement--very-long'],
                            resolvedAchievementName,
                            [
                                { minimum: 32, className: 'certificate__achievement--very-long' },
                                { minimum: 20, className: 'certificate__achievement--long' },
                            ],
                        );
                    }

                    const legacySectionsByGender = samples
                        && typeof samples.legacy_content_sections === 'object'
                            ? samples.legacy_content_sections
                            : {};
                    const draftSections = data.content_sections
                        && typeof data.content_sections === 'object'
                            ? data.content_sections
                            : (owns(data, 'content_sections')
                                && owns(legacySectionsByGender, gender)
                                && typeof legacySectionsByGender[gender] === 'object'
                                    ? legacySectionsByGender[gender]
                                    : null);
                    if (draftSections !== null) {
                        const templateValues = {
                            student_name: studentName ?? '',
                            center_name: hasCenter && typeof center.center_name === 'string'
                                ? center.center_name
                                : '—',
                            achievement_label: label ?? '',
                            achievement_name: resolvedAchievementName,
                            certificate_number: 'HMT-2026-PREVIEW',
                            plan_name: typeof achievement?.plan_name === 'string'
                                ? achievement.plan_name
                                : '',
                            plan_point_name: typeof achievement?.plan_point_name === 'string'
                                ? achievement.plan_point_name
                                : '',
                            hijri_date: '١٥ رَبِيع الأَوَّل ١٤٤٨',
                            gregorian_date: '٢٠٢٦/٠٨/٢٨',
                        };

                        contentSectionKeys.forEach((key) => {
                            const element = contentSectionElements[key];
                            const source = draftSections[key];
                            if (element && typeof source === 'string') {
                                renderPlainTemplate(element, source, templateValues);
                            }
                        });

                        setSectionLengthClass(
                            contentSectionElements.title,
                            ['certificate__title--long', 'certificate__title--very-long'],
                            [
                                { minimum: 45, className: 'certificate__title--very-long' },
                                { minimum: 32, className: 'certificate__title--long' },
                            ],
                        );
                        const quoteLength = visualLength(contentSectionElements.quote_first?.textContent)
                            + visualLength(contentSectionElements.quote_second?.textContent);
                        setSectionLengthClass(
                            document.querySelector('.certificate__quote'),
                            ['certificate__quote--long', 'certificate__quote--very-long'],
                            [
                                { minimum: 105, className: 'certificate__quote--very-long' },
                                { minimum: 75, className: 'certificate__quote--long' },
                            ],
                            quoteLength,
                        );
                        setSectionLengthClass(
                            contentSectionElements.intro,
                            ['certificate__intro--long', 'certificate__intro--very-long'],
                            [
                                { minimum: 170, className: 'certificate__intro--very-long' },
                                { minimum: 115, className: 'certificate__intro--long' },
                            ],
                        );
                        setSectionLengthClass(
                            contentSectionElements.student_line,
                            [
                                'certificate__student--long',
                                'certificate__student--very-long',
                                'certificate__student--extra-long',
                            ],
                            [
                                { minimum: 55, className: 'certificate__student--extra-long' },
                                { minimum: 36, className: 'certificate__student--very-long' },
                                { minimum: 25, className: 'certificate__student--long' },
                            ],
                        );
                        setSectionLengthClass(
                            contentSectionElements.achievement_line,
                            [
                                'certificate__achievement--long',
                                'certificate__achievement--very-long',
                            ],
                            [
                                { minimum: 155, className: 'certificate__achievement--very-long' },
                                { minimum: 105, className: 'certificate__achievement--long' },
                            ],
                        );
                        setSectionLengthClass(
                            contentSectionElements.closing,
                            ['certificate__closing--long', 'certificate__closing--very-long'],
                            [
                                { minimum: 160, className: 'certificate__closing--very-long' },
                                { minimum: 105, className: 'certificate__closing--long' },
                            ],
                        );
                    }
                });

                if (window.parent !== window) {
                    window.parent.postMessage({ type: readyType }, window.location.origin);
                }
            })();
        </script>
    @endif
</body>
</html>
