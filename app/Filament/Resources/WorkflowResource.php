<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowResource\Pages;
use App\Models\Workflow;
use App\Models\Skill;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
//use Filament\Forms\Components\TableRepeater;
use Filament\Forms\Components\TableRepeater;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationLabel = 'Munkafolyamatok';


public static function form(Forms\Form $form): Forms\Form
{
    return $form->schema([
        Forms\Components\TextInput::make('name')
            ->label('Név')->required()->unique(ignoreRecord: true),

        Forms\Components\Textarea::make('description')
            ->label('Leírás')->rows(3),

       Forms\Components\Section::make('Elvárt skill-ek')->schema([
            Forms\Components\Repeater::make('workflowSkills')
                ->relationship('workflowSkills') // hasMany a pivot modellre
                ->defaultItems(0)->minItems(0)
                ->reorderable(false)->collapsible()
                ->itemLabel(fn (array $state): ?string =>
                    Skill::find($state['skill_id'] ?? null)?->name ?? 'Új')
                ->schema([
                    Forms\Components\Grid::make(8)->schema([
                        Forms\Components\Select::make('skill_id')
                            ->label('Skill')                          // <-- emberi címke
                            ->options(function () {
                                $q = Skill::query();
                                if ($cid = Auth::user()?->company_id) {
                                    $q->where('company_id', $cid);
                                }
                                return $q->orderBy('name')->pluck('name', 'id'); // <-- NÉV jelenik meg
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('required_level')
                            ->label('Elvárt szint (1–5)')
                            ->numeric()->minValue(1)->maxValue(5)
                            ->default(1)->required()
                            ->columnSpan(2),
                    ]),
                ]),]),
    ]);
}


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('skills.name')->badge()->label('Elvárt skill-ek'),
            ])
            ->actions([ Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make() ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWorkflows::route('/'),
            'create' => Pages\CreateWorkflow::route('/create'),
            'edit'   => Pages\EditWorkflow::route('/{record}/edit'),
        ];
    }
}
