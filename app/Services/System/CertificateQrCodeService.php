<?php

namespace App\Services\System;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

class CertificateQrCodeService
{
    private const FALLBACK_FOREGROUND = '#09232A';

    private const MINIMUM_CONTRAST_RATIO = 7.0;

    private const DARKENING_STEP = 0.05;

    /**
     * @return array{qr_code_data_uri: string, verification_url: string, qr_foreground_color: string}
     */
    public function payload(string $verificationUrl, ?string $certificateColor = null): array
    {
        $foreground = $this->foregroundHex($certificateColor);

        return [
            'qr_code_data_uri' => $this->renderDataUri($verificationUrl, $foreground),
            'verification_url' => $verificationUrl,
            'qr_foreground_color' => $foreground,
        ];
    }

    public function dataUri(string $verificationUrl, ?string $certificateColor = null): string
    {
        return $this->renderDataUri(
            $verificationUrl,
            $this->foregroundHex($certificateColor),
        );
    }

    public function foregroundHex(?string $certificateColor): string
    {
        $channels = $this->hexChannels($certificateColor);
        if ($channels === null) {
            return self::FALLBACK_FOREGROUND;
        }

        for ($step = 0; $step <= 20; $step++) {
            $factor = 1 - ($step * self::DARKENING_STEP);
            $candidate = array_map(
                static fn (int $channel): int => (int) round($channel * $factor),
                $channels,
            );

            if ($this->contrastAgainstWhite($candidate) >= self::MINIMUM_CONTRAST_RATIO) {
                return sprintf('#%02X%02X%02X', ...$candidate);
            }
        }

        return self::FALLBACK_FOREGROUND;
    }

    private function renderDataUri(string $verificationUrl, string $foregroundHex): string
    {
        $foreground = $this->hexChannels($foregroundHex) ?? [9, 35, 42];
        $qrCode = new QrCode(
            data: $verificationUrl,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 360,
            margin: 24,
            roundBlockSizeMode: RoundBlockSizeMode::None,
            foregroundColor: new Color(...$foreground),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new SvgWriter)->write($qrCode, options: [
            SvgWriter::WRITER_OPTION_COMPACT => true,
            SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
        ])->getDataUri();
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function hexChannels(?string $color): ?array
    {
        if ($color === null) {
            return null;
        }

        $color = trim($color);
        if (preg_match('/\A#([0-9A-Fa-f]{6})\z/', $color, $matches) !== 1) {
            return null;
        }

        return [
            hexdec(substr($matches[1], 0, 2)),
            hexdec(substr($matches[1], 2, 2)),
            hexdec(substr($matches[1], 4, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $channels
     */
    private function contrastAgainstWhite(array $channels): float
    {
        $luminance = (0.2126 * $this->linearChannel($channels[0]))
            + (0.7152 * $this->linearChannel($channels[1]))
            + (0.0722 * $this->linearChannel($channels[2]));

        return 1.05 / ($luminance + 0.05);
    }

    private function linearChannel(int $channel): float
    {
        $normalized = $channel / 255;

        return $normalized <= 0.04045
            ? $normalized / 12.92
            : (($normalized + 0.055) / 1.055) ** 2.4;
    }
}
