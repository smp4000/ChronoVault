<?php

/**
 * =========================================================================
 * Migration: media — spatie/laravel-medialibrary (Modul 4, Tenant-DB)
 * =========================================================================
 *
 * Zweck:
 *   Medien-Tabelle der Media Library PRO TENANT — Fotos, Zertifikate und
 *   Dokumente hängen an Tenant-Entitäten (Watches, später Brands) und
 *   bleiben damit hart isoliert (ADR-007).
 *
 * Abweichung vom Paket-Stub:
 *   uuidMorphs statt morphs — unsere Domänenentitäten (Watch, Brand, …)
 *   haben UUID-Primärschlüssel; der Standard-Stub erwartet BigInt-IDs.
 *
 * DB-agnostisch (ADR-001): nur Schema-Builder.
 * =========================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // UUID-Morphs: model_id als UUID (Watch/Brand haben UUID-PKs)
            $table->uuidMorphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->nullableTimestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
