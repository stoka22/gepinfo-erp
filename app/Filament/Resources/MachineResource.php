<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MachineResource\Pages;
use App\Models\Machine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class MachineResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Törzsadatok';
    protected static ?string $navigationLabel = 'Gépek';
    protected static ?string $model = Machine::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            // company_id automatikus kitöltése a bejelentkezett user alapján
            Forms\Components\Hidden::make('company_id')
                ->default(fn () => Auth::user()?->company_id)
                ->dehydrated(fn ($state) => filled($state)),

            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->required(),

            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->required()
                // egyediség cégen BELÜL
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) =>
                        $rule->where('company_id', Auth::user()?->company_id)
                ),

            Forms\Components\TextInput::make('location')->label('Location'),
            Forms\Components\TextInput::make('vendor')->label('Vendor'),
            Forms\Components\TextInput::make('model')->label('Model'),
            Forms\Components\TextInput::make('serial')->label('Serial'),
            Forms\Components\DatePicker::make('commissioned_at')->label('Commissioned at'),
            Forms\Components\Toggle::make('active')->label('Active'),

            Forms\Components\Toggle::make('cron_enabled')
                ->label('Cron')
                ->inline(false)
                ->extraAttributes([
                    'title' => 'Cron ki/bekapcsolása (perces log generálás)',
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->grow(false),
                TextColumn::make('code')->label('Code')->badge()->copyable()->searchable(),
                TextColumn::make('location')->label('Location'),
                TextColumn::make('vendor')->label('Vendor'),
                TextColumn::make('model')->label('Model'),
                TextColumn::make('serial')->label('Serial'),
                TextColumn::make('commissioned_at')->date()->label('Commissioned at'),

                ToggleColumn::make('active')
                    ->label('Active')
                    ->alignCenter()
                    ->onColor('success')
                    ->offColor('gray')
                    ->extraAttributes(['title' => 'Aktív státusz ki/bekapcsolása'])
                    ->extraHeaderAttributes(['title' => 'Aktív státusz']),

                ToggleColumn::make('cron_enabled')
                    ->label('Cron')
                    ->alignCenter()
                    ->onColor('success')
                    ->offColor('gray')
                    ->extraAttributes(['title' => 'Cron ki/bekapcsolása (perces log generálás)'])
                    ->extraHeaderAttributes(['title' => 'Cron állapot']),
            ])
            ->headerActions([
                Tables\Actions\Action::make('enableAllCron')
                    ->label('Mind bekapcsol')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Cron bekapcsolása minden gépen')
                    ->modalDescription('Biztosan bekapcsolod a cron-t az összes gépen?')
                    // csak az aktuális cég gépein kapcsol
                    ->action(fn () =>
                        Machine::query()
                            ->where('company_id', Auth::user()?->company_id)
                            ->update(['cron_enabled' => true])
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Szerk')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip('Szerkesztés'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('cron_on')
                    ->label('Bekapcsolás (kijelöltek)')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) =>
                        $records->each->update(['cron_enabled' => true])
                    ),

                Tables\Actions\BulkAction::make('cron_off')
                    ->label('Kikapcsolás (kijelöltek)')
                    ->icon('heroicon-o-pause')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) =>
                        $records->each->update(['cron_enabled' => false])
                    ),
            ])
            ->defaultSort('name');
    }

    // Céges szűrés az index/list kérdezéseknél is (független a model scope-jától)
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();

        if (Auth::check() && Auth::user()->company_id) {
            $q->where('company_id', Auth::user()->company_id);
        }

        return $q;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMachines::route('/'),
            'create' => Pages\CreateMachine::route('/create'),
            'edit'   => Pages\EditMachine::route('/{record}/edit'),
        ];
    }
}
