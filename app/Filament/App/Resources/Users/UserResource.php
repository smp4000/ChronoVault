<?php

/**
 * =========================================================================
 * UserResource — Benutzerverwaltung im Tenant-Panel
 * =========================================================================
 *
 * Zweck:
 *   Verwaltung der Mitarbeiter eines Mandanten (Tenant-Datenbank!).
 *   Zugriff ist über App\Policies\UserPolicy geregelt: nur Benutzer mit
 *   users.*-Berechtigungen (standardmäßig Inhaber & Administratoren)
 *   sehen diese Resource überhaupt.
 *
 * Verantwortlichkeiten:
 *   - Navigation/Labels (deutsch), Global Search
 *   - Seiten-Routing; Formular/Tabelle liegen in Schemas/ bzw. Tables/
 *
 * WARUM keine Tenant-Spalte o. Ä.:
 *   Die Datenbank IST der Mandant (Multi-DB-Tenancy, ADR-007) — die
 *   users-Tabelle enthält ausschließlich Benutzer dieses Betriebs.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Filament\App\Resources\Users;

use App\Filament\App\Resources\Users\Pages\CreateUser;
use App\Filament\App\Resources\Users\Pages\EditUser;
use App\Filament\App\Resources\Users\Pages\ListUsers;
use App\Filament\App\Resources\Users\Schemas\UserForm;
use App\Filament\App\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Benutzer';

    protected static ?string $pluralModelLabel = 'Benutzer';

    protected static string|\UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
