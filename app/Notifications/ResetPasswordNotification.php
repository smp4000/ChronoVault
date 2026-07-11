<?php

/**
 * =========================================================================
 * ResetPasswordNotification — Passwort-Reset im ChronoVault-Design
 * =========================================================================
 *
 * Zweck:
 *   Ersetzt die englische Laravel/Filament-Standardmail durch die
 *   deutsche, gestaltete View emails.password-reset. Filament löst
 *   seine Notification über den Container auf (RequestPasswordReset-
 *   Page) — der Bind in AppServiceProvider::register liefert diese
 *   Klasse, die Filament-URL ($this->url) bleibt dabei erhalten.
 *
 * Gilt für BEIDE Panels (zentrales /admin und Händler-/app).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Notifications;

use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends FilamentResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Passwort zurücksetzen')
            ->view('emails.password-reset', [
                'url' => $this->url,
                'userName' => (string) ($notifiable->name ?? ''),
                // Tenant-Panel → Betriebsname; zentrales Admin → Plattform
                'brandName' => (string) (tenant('name') ?? config('app.name')),
                'expireMinutes' => (int) config('auth.passwords.users.expire', 60),
            ]);
    }
}
