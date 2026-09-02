<?php

namespace App\Services\Admin;

use App\Models\Certificate;
use App\Models\Student;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class CertificateImageRenderer
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const VIEWPORT_WIDTH = 1600;

    private const VIEWPORT_HEIGHT = 1131;

    public function __construct(
        private readonly StudentCertificateService $certificates,
    ) {}

    /**
     * Render the certificate as self-contained HTML and capture it as PNG.
     */
    public function render(Student $student, Certificate $certificate): string
    {
        if ((int) $certificate->student_id !== (int) $student->getKey()) {
            throw new LogicException('The certificate does not belong to the supplied student.');
        }

        $certificatePayload = $this->certificates->viewPayload($student, $certificate, pdf: true);
        $certificatePayload['image_mode'] = true;

        $html = view('certificates.show', [
            'certificate' => $certificatePayload,
        ])->render();

        $response = Http::withToken($this->apiToken())
            ->accept('image/png')
            ->timeout(60)
            ->post($this->endpoint(), [
                'html' => $html,
                'viewport' => [
                    'width' => self::VIEWPORT_WIDTH,
                    'height' => self::VIEWPORT_HEIGHT,
                    'deviceScaleFactor' => 1,
                    'isLandscape' => true,
                ],
                'selector' => '.certificate',
                'screenshotOptions' => [
                    'type' => 'png',
                    'fullPage' => false,
                    'captureBeyondViewport' => false,
                    'omitBackground' => false,
                ],
                'waitForSelector' => [
                    'selector' => '.certificate',
                    'visible' => true,
                    'timeout' => 10_000,
                ],
                'waitForTimeout' => 500,
            ]);

        return $this->pngBytes($response);
    }

    private function apiToken(): string
    {
        $apiToken = trim((string) config('laravel-pdf.cloudflare.api_token'));

        if ($apiToken === '') {
            throw new RuntimeException('Cloudflare Browser Rendering API token is not configured.');
        }

        return $apiToken;
    }

    private function endpoint(): string
    {
        $accountId = trim((string) config('laravel-pdf.cloudflare.account_id'));

        if ($accountId === '') {
            throw new RuntimeException('Cloudflare Browser Rendering account ID is not configured.');
        }

        return sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/browser-rendering/screenshot',
            rawurlencode($accountId),
        );
    }

    private function pngBytes(Response $response): string
    {
        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Cloudflare could not render the certificate image (HTTP %d).',
                $response->status(),
            ));
        }

        $bytes = $response->body();
        if (! str_starts_with($bytes, self::PNG_SIGNATURE)) {
            throw new RuntimeException('Cloudflare returned an invalid certificate PNG image.');
        }

        return $bytes;
    }
}
