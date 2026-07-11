<?php

/**
 * =========================================================================
 * WatermarkWatchPhotosAction — Wasserzeichen auf Uhrenfotos (Modul 4)
 * =========================================================================
 *
 * Zweck:
 *   Stempelt den Betriebsnamen (oder Wunschtext) klein, DEZENT und
 *   MITTIG auf alle Fotos einer Uhr — Schutz vor Bilderklau in Shop
 *   und Auktion. Der Text ist halbiert eingefärbt: vordere Hälfte
 *   schwarz, hintere Hälfte weiß — so bleibt er auf hellen UND dunklen
 *   Fotos erkennbar, ohne aufdringlich zu sein. Bereits gestempelte
 *   Fotos (custom_property watermarked) werden übersprungen:
 *   mehrfaches Ausführen ist sicher.
 *
 * Technik:
 *   GD + DejaVuSans (liegt via dompdf ohnehin im vendor-Ordner);
 *   Schriftgröße relativ zur Bildbreite, stark transparent. Die Datei
 *   wird im Ursprungsformat überschrieben (JPEG/PNG/WebP/GIF).
 *
 * ACHTUNG: Das Original wird ersetzt (bewusst einfach gehalten) —
 * einzelne Fotos lassen sich jederzeit neu hochladen.
 *
 * Aufrufer: EditWatch (Header-Aktion „Wasserzeichen anwenden").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Watches;

use App\Models\Watch;
use RuntimeException;
use Throwable;

class WatermarkWatchPhotosAction
{
    /**
     * @return int Anzahl der neu gestempelten Fotos
     */
    public function execute(Watch $watch, ?string $text = null): int
    {
        $text = trim($text ?? (string) tenant('name'));

        if ($text === '') {
            throw new RuntimeException('Bitte einen Wasserzeichen-Text angeben.');
        }

        $fontPath = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (! is_file($fontPath)) {
            throw new RuntimeException('Schriftdatei für das Wasserzeichen nicht gefunden.');
        }

        $stamped = 0;

        foreach ($watch->getMedia('photos') as $media) {
            if ($media->getCustomProperty('watermarked') === true) {
                continue;
            }

            $path = $media->getPath();

            if (! is_file($path)) {
                continue;
            }

            try {
                if (! $this->stampFile($path, (string) $media->mime_type, $text, $fontPath)) {
                    continue;
                }

                $media->setCustomProperty('watermarked', true);
                $media->size = (int) filesize($path);
                $media->save();

                $stamped++;
            } catch (Throwable $exception) {
                // Ein defektes Foto darf die übrigen nie blockieren
                report($exception);
            }
        }

        return $stamped;
    }

    /**
     * Text klein und dezent in die Bildmitte einbrennen — vordere
     * Texthälfte schwarz, hintere weiß. True, wenn geschrieben wurde.
     */
    private function stampFile(string $path, string $mime, string $text, string $fontPath): bool
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Klein: Schriftgröße relativ zur Bildbreite (min. 11 pt, ~2,2 %)
        $fontSize = max(11.0, $width * 0.022);

        // Text halbieren: vordere Hälfte schwarz, hintere weiß
        $length = mb_strlen($text);
        $firstHalf = mb_substr($text, 0, (int) ceil($length / 2));
        $secondHalf = mb_substr($text, (int) ceil($length / 2));

        $fullBox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $firstBox = imagettfbbox($fontSize, 0, $fontPath, $firstHalf);

        if ($fullBox === false || $firstBox === false) {
            imagedestroy($image);

            return false;
        }

        $textWidth = abs($fullBox[2] - $fullBox[0]);
        $textHeight = abs($fullBox[7] - $fullBox[1]);
        $firstWidth = abs($firstBox[2] - $firstBox[0]);

        // Mittig zentriert
        $x = (int) round(($width - $textWidth) / 2);
        $y = (int) round(($height + $textHeight) / 2);

        // Ganz dezent: stark transparent (GD-Alpha 0 = deckend, 127 = unsichtbar)
        $black = imagecolorallocatealpha($image, 0, 0, 0, 96);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 96);

        if ($black === false || $white === false) {
            imagedestroy($image);

            return false;
        }

        imagettftext($image, $fontSize, 0, $x, $y, $black, $fontPath, $firstHalf);

        if ($secondHalf !== '') {
            imagettftext($image, $fontSize, 0, $x + $firstWidth, $y, $white, $fontPath, $secondHalf);
        }

        $written = match ($mime) {
            'image/png' => imagepng($image, $path),
            'image/webp' => imagewebp($image, $path, 90),
            'image/gif' => imagegif($image, $path),
            default => imagejpeg($image, $path, 90),
        };

        imagedestroy($image);

        return $written;
    }
}
