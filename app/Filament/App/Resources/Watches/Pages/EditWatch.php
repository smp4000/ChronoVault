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
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
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
