@php
    $previewMode = (bool) ($certificate['design_preview_mode'] ?? false);
    $previewCatalog = is_array($certificate['preview_catalog'] ?? null)
        ? $certificate['preview_catalog']
        : [];
    $previewSamples = is_array($certificate['preview_samples'] ?? null)
        ? $certificate['preview_samples']
        : [];
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
    @include('certificates.partials.page')
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
