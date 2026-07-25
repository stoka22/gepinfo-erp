<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Company;
use App\Models\Employee;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\Forms\EmployeeForm;
use App\Filament\Resources\EmployeeResource\Tables\EmployeeTable;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user-group';
    protected static ?string $navigationGroup  = 'Dolgozók';
    protected static ?string $navigationLabel  = 'Dolgozók';
    protected static ?string $pluralModelLabel = 'Dolgozók';
    protected static ?string $modelLabel       = 'Dolgozó';

    public static function currentUser(): ?\App\Models\User
    {
        return Filament::auth()->user() ?? Auth::user();
    }

    public static function currentGroupId(): ?int
    {
        $u = static::currentUser();
        return $u?->company?->company_group_id ?? null;
    }

    /**
     * VISSZAADJA AZ ADOTT CÉGCSOPORTHOZ TARTOZÓ CÉGEK ID-IT.
     * FIX: a 'group_id' oszlop nem létezik; kizárólag 'company_group_id' alapján szűrünk.
     */
    public static function groupCompanyIds(?int $groupId = null): array
    {
        $gid = $groupId ?? static::currentGroupId();
        if (!$gid) {
            $co = static::currentUser()?->company;
            return $co?->id ? [$co->id] : [];
        }
        return Company::query()
            ->where('company_group_id', $gid)
            ->pluck('id')
            ->all();
    }

    public static function getRelations(): array
    {
        $rels = [];

        if (Schema::hasTable('skills') && Schema::hasTable('employee_skill')) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\SkillsRelationManager::class;
        }

        if (Schema::hasTable('time_entries')) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\TimeEntriesRelationManager::class;
        }

        if (Schema::hasTable('vacation_allowances')) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\VacationAllowancesRelationManager::class;
        }

        // Kártyák csak akkor, ha a manager létezik és a tábla is megvan
        if (
            class_exists(\App\Filament\Resources\EmployeeResource\RelationManagers\CardsRelationManager::class)
            && Schema::hasTable('employee_cards')
        ) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\CardsRelationManager::class;
        }

        return $rels; // <— NINCS beágyazás
    }


    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            //->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['company', 'companies']);

        $user = static::currentUser();
        if (! $user) {
            return $query->whereRaw('1=0');
        }
        if (($user->role ?? null) === 'admin') {
            return $query;
        }

        $companyIds = static::groupCompanyIds();
        if ($companyIds) {
            return $query->where(function (Builder $w) use ($companyIds) {
                $w->whereIn('company_id', $companyIds)
                    ->orWhereHas('companies', fn(Builder $c) => $c->whereIn('company_id', $companyIds));
            });
        }

        return $query->where('company_id', $user->company_id);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return EmployeeForm::configure($form);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return EmployeeTable::configure($table);
    }


    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'email', 'position']; // NE legyen benne 'place'
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit'   => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
