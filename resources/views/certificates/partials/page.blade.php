@php
    $previewMode = (bool) ($certificate['design_preview_mode'] ?? false);
    $showCenterIdentity = (bool) ($certificate['show_center_manager_signature'] ?? true);
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

    $sharedImageClasses = $sharedImageClasses ?? [];
    $imageAttributes = static function (string $key) use ($certificate, $sharedImageClasses): Illuminate\View\ComponentAttributeBag {
        $source = $certificate['images'][$key];

        return new Illuminate\View\ComponentAttributeBag(isset($sharedImageClasses[$source])
            ? ['class' => $sharedImageClasses[$source]]
            : ['src' => $source]);
    };
@endphp

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
                <img {{ $imageAttributes('frame')->class('certificate__frame') }}
                     data-certificate-preview-frame
                     alt=""
                     aria-hidden="true">
            @endif

            @if (($showCenterIdentity || $previewMode) && $certificate['images']['left_logo'] !== '')
                <img {{ $imageAttributes('left_logo')->class('certificate__logo certificate__logo--left') }}
                     data-certificate-preview-center-identity
                     @if ($previewMode && ! $showCenterIdentity) hidden @endif
                     alt="{{ $certificate['labels']['left_logo'] }}">
            @endif

            @if ($certificate['images']['right_logo'] !== '')
                <img {{ $imageAttributes('right_logo')->class('certificate__logo certificate__logo--right') }}
                     alt="{{ $certificate['labels']['right_logo'] }}">
            @endif

            @if (($previewMode || ! $showCenterIdentity) && $certificate['images']['right_logo'] !== '')
                <img {{ $imageAttributes('right_logo')->class('certificate__logo certificate__logo--right certificate__logo--project-left') }}
                     data-certificate-preview-project-left
                     @if ($previewMode && $showCenterIdentity) hidden @endif
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
                        <img {{ $imageAttributes('center_stamp')->class('signing__stamp') }} alt="{{ $certificate['labels']['center_stamp'] }}">
                    @endif
                    @if ($certificate['images']['center_signature'] !== '')
                        <img {{ $imageAttributes('center_signature')->class('signing__signature') }} alt="{{ $certificate['labels']['center_signature'] }}">
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
                    <img {{ $imageAttributes('project_stamp')->class('signing__stamp') }} alt="{{ $certificate['labels']['project_stamp'] }}">
                @endif
                @if ($certificate['images']['project_signature'] !== '')
                    <img {{ $imageAttributes('project_signature')->class('signing__signature') }} alt="{{ $certificate['labels']['project_signature'] }}">
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
