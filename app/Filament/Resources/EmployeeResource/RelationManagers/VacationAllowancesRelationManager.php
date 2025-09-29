<?php
// app/Filament/Resources/EmployeeResource/RelationManagers/VacationAllowancesRelationManager.php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Enums\VacationAllowanceType;
use App\Models\VacationAllowance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VacationAllowancesRelationManager extends RelationManager
{
    
    protected static string $relationship = 'vacationAllowances';
    protected static string $slug = 'vacation-allowances';
    protected static ?string $title = 'Pótszabadságok';

    
   

    protected function getTableQuery(): Builder
    {
        return VacationAllowance::query()->where('employee_id', $this->getOwnerRecord()->id);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('employee_id')
                ->default(fn () => $this->getOwnerRecord()->id)
                ->dehydrated(),
            Forms\Components\Hidden::make('company_id')
                ->default(fn () => Auth::user()?->company_id)
                ->dehydrated(),

            Forms\Components\TextInput::make('year')->numeric()->minValue(2000)->maxValue(2100)
                ->default(fn () => now()->year)->required(),

            Forms\Components\Select::make('type')->label('Típus')->options([
                VacationAllowanceType::Child->value        => 'Gyermek(ek)',
                VacationAllowanceType::Disability->value   => 'Fogyatékosság',
                VacationAllowanceType::Under18->value      => '18 év alatt',
                VacationAllowanceType::SingleParent->value => 'Egyedülálló szülő',
                VacationAllowanceType::Other->value        => 'Egyéb',
            ])->required(),

            Forms\Components\TextInput::make('days')->label('Napok')
                ->numeric()->minValue(0)->step(0.5)->required(),

            Forms\Components\TextInput::make('note')->label('Megjegyzés')->maxLength(255),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('year')->label('Év')->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Típus')
                    ->formatStateUsing(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'child' => 'Gyermek(ek)', 'disability' => 'Fogyatékosság', 'under18' => '18 év alatt',
                        'single_parent' => 'Egyedülálló', 'other' => 'Egyéb', default => (string) $state,
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('days')->numeric(1)->label('Napok')->sortable(),
                Tables\Columns\TextColumn::make('note')->label('Megjegyzés')->limit(40),
            ])
            ->headerActions([ Tables\Actions\CreateAction::make() ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('year', 'desc');
    }
}
