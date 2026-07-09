<?php

/**
 * =========================================================================
 * DatabaseSeeder — Zentraler Plattform-Administrator
 * =========================================================================
 *
 * Zweck:
 *   Legt den zentralen Admin-Benutzer an (Login /admin) — idempotent
 *   (firstOrCreate) und OHNE Factory/Faker, damit der Seeder auch in
 *   Produktion läuft (faker ist require-dev und dort nicht installiert).
 *
 * Zugangsdaten:
 *   Aus der .env (CENTRAL_ADMIN_EMAIL / CENTRAL_ADMIN_PASSWORD) —
 *   Fallback admin@admin.com / password für die lokale Entwicklung.
 *   In PRODUKTION: entweder echte Werte in der .env setzen ODER das
 *   Passwort sofort nach dem ersten Login ändern!
 * =========================================================================
 */

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => (string) env('CENTRAL_ADMIN_EMAIL', 'admin@admin.com')],
            [
                'name' => 'Administrator',
                'password' => Hash::make((string) env('CENTRAL_ADMIN_PASSWORD', 'password')),
            ],
        );
    }
}
