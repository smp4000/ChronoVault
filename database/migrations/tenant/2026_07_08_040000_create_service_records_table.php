<?php

/**
 * =========================================================================
 * Migration: service_records — Service-Historie & Wartung (Modul 6)
 * =========================================================================
 *
 * Zweck:
 *   Servicevorgänge pro Uhr (Revision, Reparatur, Politur, …) mit
 *   Werkstatt (contacts!), Kosten, Zeitraum und Service-Garantie.
 *
 * Design-Entscheidungen:
 *   - previous_watch_status: Beim Einreichen wird der aktuelle
 *     Uhren-Status gemerkt und beim Abschluss WIEDERHERGESTELLT —
 *     eine Kommissionsuhr kommt als Kommission zurück, nicht "An Lager".
 *   - contact_id restrictOnDelete: Werkstatt-Bezüge bleiben erhalten
 *     (Referenz-Schutz zusätzlich in der ContactPolicy).
 *   - currency wie bei transactions (Ewigkeitsdaten).
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
        Schema::create('service_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('watch_id')->constrained('watches')->restrictOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type'); // ServiceType-Enum
            $table->string('status'); // ServiceStatus-Enum
            $table->string('previous_watch_status')->nullable(); // Restore beim Abschluss
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->date('submitted_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('document_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['watch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_records');
    }
};
