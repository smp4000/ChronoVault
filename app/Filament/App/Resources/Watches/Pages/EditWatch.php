<?php

/**
 * =========================================================================
 * EditWatch — Uhr bearbeiten (+ Wasserzeichen-Aktion)
 * =========================================================================
 * Header-Aktion „Wasserzeichen anwenden": stempelt den Betriebsnamen
 * (oder Wunschtext) auf alle Fotos der Uhr (WatermarkWatchPhotosAction,
 * bereits gestempelte werden übersprungen).
 * =========================================================================
 */

namespace App\Filament\App\Resources\Watches\Pages;

use App\Actions\Watches\WatermarkWatchPhotosAction;
use App\Filament\App\Resources\Watches\WatchResource;
use App\Models\Watch;
use App\Services\InventoryReportService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;

class EditWatch extends EditRecord
{
    protected static string $resource = WatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('certificate')
                ->label('Zertifikat (PDF)')
                ->icon('heroicon-m-check-badge')
                ->color('gray')
                ->modalHeading('Wert- und Bestandszertifikat erstellen')
                ->modalDescription('Zertifikat im Versicherungs-Stil: Kenndaten, Seriennummer, Versicherungswert und Foto-Dokumentation — zum Ausdrucken und Unterschreiben.')
                ->modalSubmitActionLabel('Zertifikat erstellen')
                ->form([
                    Textarea::make('issued_for')
                        ->label('Eigentümer(in) / Ausgestellt für')
                        ->rows(3)
                        ->default(fn (): string => implode("\n", array_filter([
                            (string) tenant('name'),
                            tenant('company_street'),
                            trim((string) tenant('company_postal_code').' '.(string) tenant('company_city')),
                        ])))
                        ->helperText('Frei anpassbar — z. B. Name und Anschrift des Kunden.'),

                    Toggle::make('include_purchase')
                        ->label('Kaufdatum und Kaufpreis ausweisen')
                        ->default(true),

                    Toggle::make('with_documents')
                        ->label('Original-Belege anhängen')
                        ->helperText('Hinterlegte Dokumente der Uhr (Kaufrechnungen, Zertifikate — PDF und Bild) werden mit eingeheftet.')
                        ->default(true),

                    Toggle::make('mask_serial')
                        ->label('Seriennummer teilweise schwärzen'),
                ])
                ->action(fn (Watch $record, array $data) => response()->streamDownload(
                    function () use ($record, $data): void {
                        echo app(InventoryReportService::class)->renderCertificatePdf(
                            $record,
                            filled($data['issued_for'] ?? null) ? (string) $data['issued_for'] : null,
                            (bool) ($data['include_purchase'] ?? true),
                            (bool) ($data['mask_serial'] ?? false),
                            (bool) ($data['with_documents'] ?? true),
                        );
                    },
                    'Zertifikat-'.($record->reference_number ?? $record->getKey()).'.pdf',
                    ['Content-Type' => 'application/pdf'],
                )),

            Action::make('watermark')
                ->label('Wasserzeichen')
                ->icon('heroicon-m-shield-check')
                ->color('gray')
                ->visible(fn (Watch $record): bool => $record->getMedia('photos')->isNotEmpty())
                ->modalHeading('Wasserzeichen auf alle Fotos anwenden')
                ->modalDescription('Der Text wird klein und dezent in die Bildmitte eingebrannt — vordere Hälfte schwarz, hintere weiß (Schutz vor Bilderklau). Bereits gestempelte Fotos werden übersprungen. Achtung: Das Original wird ersetzt.')
                ->modalSubmitActionLabel('Wasserzeichen anwenden')
                ->form([
                    TextInput::make('text')
                        ->label('Wasserzeichen-Text')
                        ->default(fn (): string => (string) tenant('name'))
                        ->required()
                        ->maxLength(60),

                    Toggle::make('force')
                        ->label('Auch bereits gestempelte Fotos erneut stempeln')
                        ->helperText('Achtung: Ein vorhandener Stempel bleibt erhalten — der neue kommt zusätzlich dazu.'),
                ])
                ->action(function (Watch $record, array $data): void {
                    try {
                        $count = app(WatermarkWatchPhotosAction::class)->execute(
                            $record,
                            (string) $data['text'],
                            (bool) ($data['force'] ?? false),
                        );
                    } catch (RuntimeException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title($count > 0
                            ? $count.' '.($count === 1 ? 'Foto' : 'Fotos').' mit Wasserzeichen versehen'
                            : 'Alle Fotos tragen bereits ein Wasserzeichen')
                        ->send();
                }),

            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
