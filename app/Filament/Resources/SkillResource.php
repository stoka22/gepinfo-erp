<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SkillResource\Pages;
use App\Models\Skill;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Skill-ek';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            // Rejtett company_id – create-nél automatikusan kitöltjük
            Forms\Components\Hidden::make('company_id')
                ->default(fn () => Auth::user()?->company_id)
                ->dehydrated(fn ($state) => filled($state)),

            Forms\Components\TextInput::make('name')
                ->label('Név')
                ->required()
                // Egyediség cégen BELÜL
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where(
                        'company_id',
                        Auth::user()?->company_id
                    )
                ),

            Forms\Components\TextInput::make('category')->label('Kategória'),
            Forms\Components\Textarea::make('description')->label('Leírás')->rows(3),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->label('Kategória')->sortable(),
                // Ajánlott minta: *_count alias
                Tables\Columns\TextColumn::make('workflows_count')
                    ->counts('workflows')
                    ->label('Workflow-k száma'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    // Biztos, céges szűrés (akkor is, ha a model globális scope-ja le lenne véve)
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::check() && Auth::user()->company_id) {
            $query->where('company_id', Auth::user()->company_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSkills::route('/'),
            'create' => Pages\CreateSkill::route('/create'),
            'edit'   => Pages\EditSkill::route('/{record}/edit'),
        ];
    }
}
