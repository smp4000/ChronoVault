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
        $email = env('CENTRAL_ADMIN_EMAIL');
        $password = env('CENTRAL_ADMIN_PASSWORD');

        // SICHERHEIT (Audit 2026-07-22): In Produktion NIEMALS mit den
        // öffentlich bekannten Fallback-Zugangsdaten seeden. Wer der
        // Deployment-Anleitung folgt, MUSS echte Werte in der .env setzen —
        // sonst bricht der Seeder hart ab, statt /admin mit
        // admin@admin.com/password zu öffnen.
        if (app()->environment('production') && (blank($email) || blank($password))) {
            throw new \RuntimeException(
                'Seeder abgebrochen: CENTRAL_ADMIN_EMAIL und CENTRAL_ADMIN_PASSWORD müssen '
                .'in der Produktions-.env gesetzt sein (siehe docs/DEPLOYMENT.md).'
            );
        }

        User::firstOrCreate(
            ['email' => (string) ($email ?: 'admin@admin.com')],
            [
                'name' => 'Administrator',
                'password' => Hash::make((string) ($password ?: 'password')),
            ],
        );
    }
}
