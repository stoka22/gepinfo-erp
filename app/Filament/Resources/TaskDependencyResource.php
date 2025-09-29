<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskDependencyResource\Pages;
use App\Models\Task;
use App\Models\TaskDependency;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\ViewColumn;



class TaskDependencyResource extends Resource
{
    protected static ?string $model = TaskDependency::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
   // protected static ?string $navigationGroup = 'Tervezés';
   protected static ?string $navigationGroup = 'Termelés';
    protected static ?string $modelLabel = 'Függőség';
    protected static ?string $pluralModelLabel = 'Függőségek';

    
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('predecessor_id')
                ->label('Elődfeladat')
                ->options(fn() => Task::query()
                    ->orderBy('starts_at')
                    ->get()
                    ->mapWithKeys(fn($t)=>[$t->id => "{$t->name} (#{$t->id}) {$t->starts_at} → {$t->ends_at}"]))
                ->searchable()->required(),

            Forms\Components\Select::make('successor_id')
                ->label('Utódfeladat')
                ->options(fn() => Task::query()
                    ->orderBy('starts_at')
                    ->get()
                    ->mapWithKeys(fn($t)=>[$t->id => "{$t->name} (#{$t->id}) {$t->starts_at} → {$t->ends_at}"]))
                ->searchable()->required()
                ->different('predecessor_id'),

            Forms\Components\Select::make('type')
                ->label('Típus')
                ->options(['FS' => 'Finish → Start'])
                ->default('FS')->required(),

            Forms\Components\TextInput::make('lag_minutes')
                ->label('Lag (perc, lehet negatív)')
                ->numeric()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
        //->defaultGroup('predecessor.orderItem.item.name')
        ->recordUrl(null)      // ← sorra kattintva ne menjen szerkesztésre
        ->recordAction(null)
        
        ->columns([
            Tables\Columns\TextColumn::make('predecessor.orderItem.item.sku')
                ->label('Termék')
                
                ->sortable()
                ->searchable()
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->wrap(false),

            // GYÁRTANDÓ DB
            Tables\Columns\TextColumn::make('predecessor.orderItem.qty_ordered')
                ->label('Gyártandó (db)')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', ' '))
                ->alignRight()
                ->sortable()
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->wrap(false),

            // KEZDÉS – az elődfeladat kezdete
            Tables\Columns\TextColumn::make('predecessor.starts_at')
                ->label('Kezdés')
                ->dateTime('Y.m.d H:i')
                ->sortable()
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->wrap(false),

            // BEFEJEZÉS – az utódfeladat vége
            Tables\Columns\TextColumn::make('successor.ends_at')
                ->label('Befejezés')
                ->dateTime('Y.m.d H:i')
                ->sortable()
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->wrap(false),

            // (ha kell még egy pillantásra) típus/lag badge-ben, de egy sorban marad
            Tables\Columns\TextColumn::make('type')
                ->label('')
                ->badge()
                ->color('warning')
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->wrap(false),

            Tables\Columns\TextColumn::make('lag_minutes')
                ->label('Lag (perc)')
                ->alignRight()
                ->extraAttributes(['class' => 'whitespace-nowrap'])
                ->wrap(false),
            Panel::make([
                ViewColumn::make('chain')
                    ->label('Függőséglánc (termék)')
                    ->view('filament.tables.columns.dependency-chain'), // vagy a -inline nézeted
            ])
                ->collapsible()
                ->collapsed(true),
                ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Szerkesztés')
                    ->extraAttributes(['x-on:click.stop' => '']),
                Tables\Actions\DeleteAction::make()
                    ->label('Törlés')
                    ->extraAttributes(['x-on:click.stop' => '']),
                ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaskDependencies::route('/'),
            'create' => Pages\CreateTaskDependency::route('/create'),
            'edit' => Pages\EditTaskDependency::route('/{record}/edit'),
        ];
    }
}
