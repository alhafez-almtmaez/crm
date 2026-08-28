@php
    $previewMode = (bool) ($certificate['design_preview_mode'] ?? false);
    $showCenterIdentity = (bool) ($certificate['show_center_manager_signature'] ?? true);
    $previewCatalog = is_array($certificate['preview_catalog'] ?? null)
        ? $certificate['preview_catalog']
        : [];
    $previewSamples = is_array($certificate['preview_samples'] ?? null)
        ? $certificate['preview_samples']
        : [];
    $studentName = (string) ($certificate['student_name'] ?? '');
    $studentNameLength = mb_strlen($studentName);
    $studentNameClass = $studentNameLength > 55
        ? 'certificate__student--extra-long'
        : ($studentNameLength > 36
            ? 'certificate__student--very-long'
            : ($studentNameLength > 25 ? 'certificate__student--long' : ''));

    $achievementName = (string) ($certificate['achievement_name'] ?? '');
    $achievementNameClass = mb_strlen($achievementName) > 32
        ? 'certificate__achievement--very-long'
        : (mb_strlen($achievementName) > 20 ? 'certificate__achievement--long' : '');

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
                <img @class([
                         'certificate__logo',
                         'certificate__logo--right',
                         'certificate__logo--project-solo' => ! $showCenterIdentity,
                     ])
                     data-certificate-preview-project-logo
                     src="{{ $certificate['images']['right_logo'] }}"
                     alt="{{ $certificate['labels']['right_logo'] }}">
            @endif

            <h1 class="certificate__title">{{ $certificate['title'] }}</h1>

            <div class="certificate__quote" aria-label="{{ $certificate['labels']['poem'] }}">
                <span>{{ $certificate['quote_first'] }}</span>
                <span class="certificate__quote-ornament" aria-hidden="true">✧✦✧</span>
                <span>{{ $certificate['quote_second'] }}</span>
            </div>

            <p class="certificate__intro">
                <span data-certificate-preview-intro-before-project>{{ $certificate['intro_before_project'] }}</span>
                <strong data-certificate-preview-project-name>{{ $certificate['project_name'] }}</strong>
                - <strong data-certificate-preview-center-name>{{ $certificate['center_name'] }}</strong> -
                <span data-certificate-preview-intro-after-center>{{ $certificate['intro_after_center'] }}</span>
            </p>

            <p class="certificate__student {{ $studentNameClass }}">
                <span class="ornament">﴿</span>
                <strong data-certificate-preview-student>{{ $studentName }}</strong>
                <span class="ornament">﴾</span>
            </p>

            <p class="certificate__achievement {{ $achievementNameClass }}">
                <span data-certificate-preview-achievement-intro>{{ $certificate['achievement_intro'] }}</span>
                <span data-certificate-preview-achievement-label>{{ $certificate['achievement_label'] }}</span>
                <span class="ornament">﴿</span>
                <strong data-certificate-preview-achievement-name>{{ $achievementName }}</strong>
                <span class="ornament">﴾</span>
                <span data-certificate-preview-achievement-suffix>{{ $certificate['achievement_suffix'] }}</span>
            </p>

            <p class="certificate__closing" data-certificate-preview-closing>{{ $certificate['closing_text'] }}</p>

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
                const root = document.querySelector('.certificate-page');
                const certificateElement = document.querySelector('[data-certificate-preview-certificate]');
                const frame = document.querySelector('[data-certificate-preview-frame]');
                const centerName = document.querySelector('[data-certificate-preview-center-name]');
                const centerIdentityElements = document.querySelectorAll('[data-certificate-preview-center-identity]');
                const projectLogo = document.querySelector('[data-certificate-preview-project-logo]');
                const projectSigning = document.querySelector('[data-certificate-preview-project-signing]');
                const student = document.querySelector('[data-certificate-preview-student]');
                const achievementLabel = document.querySelector('[data-certificate-preview-achievement-label]');
                const achievementName = document.querySelector('[data-certificate-preview-achievement-name]');
                const wordingElements = {
                    project_name: document.querySelector('[data-certificate-preview-project-name]'),
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
                const setLengthClass = (element, classes, value, thresholds) => {
                    const container = element?.parentElement;
                    if (!container) return;

                    container.classList.remove(...classes);
                    const length = Array.from(value).length;
                    const match = thresholds.find(({ minimum }) => length > minimum);
                    if (match) container.classList.add(match.className);
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

                    if (center) {
                        if (centerName
                            && typeof center.center_name === 'string'
                            && center.center_name.trim() !== '') {
                            centerName.textContent = center.center_name;
                        }

                        if (typeof center.show_center_manager_signature === 'boolean') {
                            const showCenterIdentity = center.show_center_manager_signature;

                            certificateElement?.classList.toggle('certificate--project-only', !showCenterIdentity);
                            projectLogo?.classList.toggle('certificate__logo--project-solo', !showCenterIdentity);
                            projectSigning?.classList.toggle('certificate__signing--project-solo', !showCenterIdentity);
                            centerIdentityElements.forEach((element) => {
                                element.hidden = !showCenterIdentity;
                            });
                        }
                    }

                    const gender = center && ['male', 'female'].includes(center.student_gender)
                        ? center.student_gender
                        : '';
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
                });

                if (window.parent !== window) {
                    window.parent.postMessage({ type: readyType }, window.location.origin);
                }
            })();
        </script>
    @endif
</body>
</html>
