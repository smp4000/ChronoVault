<?php

/**
 * =========================================================================
 * WatermarkWatchPhotosAction — Wasserzeichen auf Uhrenfotos (Modul 4)
 * =========================================================================
 *
 * Zweck:
 *   Stempelt den Betriebsnamen (oder Wunschtext) halbtransparent unten
 *   rechts auf alle Fotos einer Uhr — Schutz vor Bilderklau in Shop
 *   und Auktion. Bereits gestempelte Fotos (custom_property
 *   watermarked) werden übersprungen: mehrfaches Ausführen ist sicher.
 *
 * Technik:
 *   GD + DejaVuSans (liegt via dompdf ohnehin im vendor-Ordner);
 *   Schriftgröße relativ zur Bildbreite, weißer Text mit dunklem
 *   Schatten für Lesbarkeit auf hellen UND dunklen Fotos. Die Datei
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
     * Text unten rechts einbrennen — true, wenn die Datei geschrieben wurde.
     */
    private function stampFile(string $path, string $mime, string $text, string $fontPath): bool
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Schriftgröße relativ zur Bildbreite (min. 12 pt, ~2,8 %)
        $fontSize = max(12.0, $width * 0.028);
        $margin = (int) round($fontSize * 0.9);

        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        if ($box === false) {
            imagedestroy($image);

            return false;
        }

        $textWidth = abs($box[2] - $box[0]);
        $x = max($margin, $width - $textWidth - $margin);
        $y = $height - $margin;

        // Dunkler Schatten + halbtransparentes Weiß = lesbar auf allem
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 70);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 40);

        if ($shadow === false || $white === false) {
            imagedestroy($image);

            return false;
        }

        imagettftext($image, $fontSize, 0, $x + 2, $y + 2, $shadow, $fontPath, $text);
        imagettftext($image, $fontSize, 0, $x, $y, $white, $fontPath, $text);

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
