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
                ->modalHeading('Bestands- und Wertübersicht erstellen')
                ->modalDescription('PDF mit allen Uhren im Bestand: Foto, Referenz, Seriennummer, Zustand und Wiederbeschaffungswert — für Versicherung, Bank oder eigene Unterlagen.')
                ->modalSubmitActionLabel('PDF erstellen')
                ->form([
                    Toggle::make('include_consignment')
                        ->label('Kommissionsuhren einbeziehen')
                        ->helperText('Fremdeigentum wird in der Liste gekennzeichnet.'),

                    Toggle::make('include_purchase')
                        ->label('Einkaufspreise ausweisen')
                        ->helperText('Nur für interne Zwecke sinnvoll — Versicherungen brauchen die Einkaufspreise nicht.'),
                ])
                ->action(fn (array $data) => response()->streamDownload(
                    function () use ($data): void {
                        echo app(InventoryReportService::class)->renderPdf(
                            (bool) ($data['include_consignment'] ?? false),
                            (bool) ($data['include_purchase'] ?? false),
                        );
                    },
                    'Bestandsliste-'.now()->format('Y-m-d').'.pdf',
                    ['Content-Type' => 'application/pdf'],
                )),

            CreateAction::make(),
        ];
    }
}
