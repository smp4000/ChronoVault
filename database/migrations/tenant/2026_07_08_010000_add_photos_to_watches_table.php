<?php

/**
 * =========================================================================
 * Migration: watches.photos — gespeicherte Uhrenfotos (Modul 3 → 4)
 * =========================================================================
 *
 * Zweck:
 *   JSON-Array mit Pfaden (public-Disk, tenant-isoliert durch den
 *   FilesystemTenancyBootstrapper) der zur Uhr gespeicherten Fotos.
 *   Befüllt zunächst automatisch aus den KI-Bildquellen
 *   (DownloadWatchPhotosAction); der manuelle/geführte Upload folgt
 *   in Modul 4 (photo_slots existiert dafür bereits).
 * =========================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('photo_slots');
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
