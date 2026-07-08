<?php

/**
 * =========================================================================
 * ContactResource — Kundenstamm im Tenant-Panel (Modul 5)
 * =========================================================================
 *
 * Zweck:
 *   Verwaltung der Kontakte (Käufer, Verkäufer/Lieferanten, Einlieferer).
 *   Zugriff regelt App\Policies\ContactPolicy (contacts.*).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Contacts;

use App\Filament\App\Resources\Contacts\Pages\CreateContact;
use App\Filament\App\Resources\Contacts\Pages\EditContact;
use App\Filament\App\Resources\Contacts\Pages\ListContacts;
use App\Filament\App\Resources\Contacts\Schemas\ContactForm;
use App\Filament\App\Resources\Contacts\Tables\ContactsTable;
use App\Models\Contact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'Kontakt';

    protected static ?string $pluralModelLabel = 'Kontakte';

    protected static string|\UnitEnum|null $navigationGroup = 'Verkauf';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Schema $schema): Schema
    {
        return ContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactsTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['company_name', 'first_name', 'last_name', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Contact $record */
        return $record->displayName();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
