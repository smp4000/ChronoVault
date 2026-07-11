<?php

/**
 * =========================================================================
 * ListWatches — Bestandsliste mit Versicherungs-PDF-Export
 * =========================================================================
 * Header-Aktion „Versicherungsliste (PDF)": Bestands- und Wertübersicht
 * über den InventoryReportService (Wiederbeschaffungswerte, Summe,
 * Stichtag) — optional inkl. Kommissionsware und Einkaufspreisen.
 * =========================================================================
 */

namespace App\Filament\App\Resources\Watches\Pages;

use App\Filament\App\Resources\Watches\WatchResource;
use App\Services\InventoryReportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;

class ListWatches extends ListRecords
{
    protected static string $resource = WatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('insuranceReport')
                ->label('Versicherungsliste (PDF)')
                ->icon('heroicon-m-document-arrow-down')
                ->color('gray')
                ->modalHeading('Versicherungsmappe erstellen')
                ->modalDescription('PDF für die Versicherung: vorne die Übersicht aller Uhren im Eigentum mit Wiederbeschaffungswerten, dahinter je Uhr das komplette Wert-Zertifikat mit Foto-Dokumentation.')
                ->modalSubmitActionLabel('PDF erstellen')
                ->form([
                    Textarea::make('issued_for')
                        ->label('Eigentümer(in) / Ausgestellt für')
                        ->rows(3)
                        ->default(fn (): string => implode("\n", array_filter([
                            (string) tenant('name'),
                            tenant('company_street'),
                            trim((string) tenant('company_postal_code').' '.(string) tenant('company_city')),
                        ])))
                        ->helperText('Steht auf jedem Zertifikat der Mappe — frei anpassbar.'),

                    Toggle::make('with_certificates')
                        ->label('Wert-Zertifikate anhängen')
                        ->helperText('Je Eigentums-Uhr ein Zertifikat mit Titelbild und Foto-Seite (Kommissionsware bekommt keins).')
                        ->default(true),

                    Toggle::make('with_documents')
                        ->label('Original-Belege anhängen')
                        ->helperText('Hinterlegte Dokumente je Uhr (Kaufrechnungen, Zertifikate — PDF und Bild) werden mit eingeheftet.')
                        ->default(true),

                    Toggle::make('include_consignment')
                        ->label('Kommissionsuhren einbeziehen')
                        ->helperText('Fremdeigentum wird in der Übersicht gekennzeichnet.'),

                    Toggle::make('include_purchase')
                        ->label('Einkaufspreise ausweisen')
                        ->helperText('Für die Versicherungs-Dokumentation üblich (Checkliste); abschaltbar für externe Zwecke.')
                        ->default(true),

                    Toggle::make('mask_serial')
                        ->label('Seriennummern teilweise schwärzen')
                        ->helperText('Zeigt nur die ersten und letzten beiden Zeichen (z. B. 52••••Y0).'),
                ])
                ->action(fn (array $data) => response()->streamDownload(
                    function () use ($data): void {
                        echo app(InventoryReportService::class)->renderPdf(
                            (bool) ($data['include_consignment'] ?? false),
                            (bool) ($data['include_purchase'] ?? false),
                            (bool) ($data['mask_serial'] ?? false),
                            (bool) ($data['with_certificates'] ?? true),
                            filled($data['issued_for'] ?? null) ? (string) $data['issued_for'] : null,
                            (bool) ($data['with_documents'] ?? true),
                        );
                    },
                    'Bestandsliste-'.now()->format('Y-m-d').'.pdf',
                    ['Content-Type' => 'application/pdf'],
                )),

            CreateAction::make(),
        ];
    }
}
