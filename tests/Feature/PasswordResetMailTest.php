<?php

/**
 * =========================================================================
 * PasswordResetMailTest — Passwort-Reset im ChronoVault-Design
 * =========================================================================
 *
 * Abgedeckt:
 *   - Container liefert die eigene Notification statt der Filament-
 *     Standardklasse (Bind in AppServiceProvider)
 *   - toMail() nutzt die deutsche View mit Filament-URL, Anrede,
 *     Button und Gültigkeitshinweis
 * =========================================================================
 */

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;

it('sends the password reset mail in chronovault design', function () {
    // Filament löst die Notification über den Container auf —
    // der Bind muss die eigene Klasse liefern
    $notification = app(FilamentResetPassword::class, ['token' => 'test-token']);

    expect($notification)->toBeInstanceOf(ResetPasswordNotification::class);

    $notification->url = 'https://lsw-chrono.chrono-save.de/app/password-reset/reset?token=test-token';

    $user = new User([
        'name' => 'Christian Welle',
        'email' => 'christian@example.test',
    ]);

    $mailMessage = $notification->toMail($user);

    expect($mailMessage->view)->toBe('emails.password-reset');

    $html = view($mailMessage->view, $mailMessage->viewData)->render();

    expect($html)->toContain('Passwort zurücksetzen')
        ->and($html)->toContain('Guten Tag Christian Welle')
        ->and($html)->toContain('Neues Passwort vergeben')
        ->and($html)->toContain('password-reset/reset?token=test-token')
        ->and($html)->toContain('Minuten gültig')
        ->and($html)->toContain('einfach ignorieren');
});
