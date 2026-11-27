<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Limity nahrávania z nastavení PHP a zrozumiteľné chybové správy pre web aj API.
 *
 * - upload_max_filesize: najviac na jeden súbor
 * - post_max_size: najviac na celý request (všetky fotky + polia formulára)
 */
class UploadLimits
{
    /**
     * Povolené typy fotiek — určené podľa OBSAHU súboru (finfo), nie podľa názvu či hlavičky od klienta.
     * Nikdy sem nepridávaj image/svg+xml: SVG môže obsahovať JavaScript (XSS z našej domény).
     * Rovnaké typy ako vo webových formulároch. HEIC/AVIF zámerne nie: väčšina prehliadačov ich
     * nezobrazí a ImageLoader z nich nevie spraviť náhľad (appka posiela vždy JPEG).
     */
    public const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly ?int $maxFileBytes = null,
        private readonly ?int $maxPostBytes = null,
    ) {
    }

    public function maxFileBytes(): int
    {
        return $this->maxFileBytes ?? self::iniToBytes((string) ini_get('upload_max_filesize'));
    }

    /** 0 = bez obmedzenia */
    public function maxPostBytes(): int
    {
        return $this->maxPostBytes ?? self::iniToBytes((string) ini_get('post_max_size'));
    }

    /**
     * Prvý súbor, ktorý sa nenahral (priveľký, prerušený prenos…), alebo null.
     *
     * @param iterable<mixed> $files
     */
    public function findInvalidFile(iterable $files): ?UploadedFile
    {
        foreach (FotoUploader::flattenFiles($files) as $file) {
            if (!$file->isValid()) {
                return $file;
            }
        }

        return null;
    }

    public static function isAllowedImage(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), self::ALLOWED_IMAGE_TYPES, true);
    }

    /**
     * Prvý (inak platne nahraný) súbor, ktorý nie je povolená fotka — napr. HTML, SVG, PHP, PDF.
     *
     * @param iterable<mixed> $files
     */
    public function findUnsupportedFile(iterable $files): ?UploadedFile
    {
        foreach (FotoUploader::flattenFiles($files) as $file) {
            if ($file->isValid() && !self::isAllowedImage($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array{code: string, message: string}
     */
    public function unsupportedApiError(UploadedFile $file): array
    {
        return [
            'code' => 'photo_unsupported_type',
            'message' => sprintf('File "%s" is not a supported photo (JPEG, PNG, WebP, GIF).', $file->getClientOriginalName()),
        ];
    }

    public function unsupportedWebError(UploadedFile $file): string
    {
        return sprintf('Súbor „%s“ nie je fotka. Povolené sú JPEG, PNG, WebP a GIF.', $file->getClientOriginalName());
    }

    /**
     * Chyba pre API (anglicky, ako ostatné chyby API) — code je stabilný identifikátor pre appku.
     *
     * @return array{code: string, message: string}
     */
    public function apiError(UploadedFile $file): array
    {
        if ($this->isTooLarge($file)) {
            return [
                'code' => 'photo_too_large',
                'message' => sprintf('Photo "%s" is too large (max %s).', $file->getClientOriginalName(), self::formatMb($this->maxFileBytes())),
            ];
        }

        return [
            'code' => 'photo_upload_failed',
            'message' => sprintf('Photo "%s" could not be uploaded (error %d).', $file->getClientOriginalName(), $file->getError()),
        ];
    }

    /**
     * Chyba pre web (slovensky).
     */
    public function webError(UploadedFile $file): string
    {
        if ($this->isTooLarge($file)) {
            return sprintf('Fotka „%s“ je príliš veľká (najviac %s). Skús menšiu fotku.', $file->getClientOriginalName(), self::formatMb($this->maxFileBytes()));
        }

        return sprintf('Fotku „%s“ sa nepodarilo nahrať. Skús to znova.', $file->getClientOriginalName());
    }

    public static function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function formatMb(int $bytes): string
    {
        $mb = $bytes / 1024 / 1024;

        return ($mb == (int) $mb ? (int) $mb : round($mb, 1)) . ' MB';
    }

    private function isTooLarge(UploadedFile $file): bool
    {
        return in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    }
}
