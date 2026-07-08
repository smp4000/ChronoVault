<?php

/**
 * =========================================================================
 * Migration: contacts & transactions — Kauf/Verkauf (Modul 5, Tenant-DB)
 * =========================================================================
 *
 * Zweck:
 *   - contacts    : Kundenstamm des Betriebs (Käufer, Verkäufer/Lieferanten,
 *                   Einlieferer) — Privatpersonen wie Firmen.
 *   - transactions: An- und Verkäufe pro Uhr — die PREISHISTORIE. Eine Uhr
 *                   kann mehrfach den Besitzer wechseln (Ankauf → Verkauf →
 *                   Rückkauf → …), deshalb eigene Tabelle statt Feldern
 *                   an der Uhr (watches.purchase_* bleibt der Schnellzugriff
 *                   auf den AKTUELLEN Einkauf und wird synchron gehalten).
 *
 * Design-Entscheidungen:
 *   - restrictOnDelete auf watch_id UND contact_id: Belege dürfen ihre
 *     Bezüge nie verlieren (Policies verhindern das Löschen zusätzlich
 *     in der UI; SoftDeletes decken das Archivieren ab).
 *   - currency (ISO 4217, Default EUR): heute nur EUR, aber Belege sind
 *     Ewigkeitsdaten — nachträglich eine Währung raten zu müssen wäre fatal.
 *   - price als decimal(12,2): ausreichend bis 9.999.999.999,99.
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
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // ContactType-Enum
            $table->string('company_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('watch_id')->constrained('watches')->restrictOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type'); // TransactionType-Enum (purchase/sale)
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('transacted_at');
            $table->string('payment_method')->nullable(); // PaymentMethod-Enum
            $table->string('document_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Historien-Abfragen: "alle Verkäufe dieser Uhr", Kennzahlen je Typ
            $table->index(['watch_id', 'type']);
            $table->index('transacted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('contacts');
    }
};
