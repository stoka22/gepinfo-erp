<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Machine;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\PartnerOrder;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Validation\Rules\Unique;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


class PartnerOrderResource extends Resource
{
    protected static ?string $model = PartnerOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Értékesítés';
    protected static ?string $modelLabel = 'Partner megrendelés';
    protected static ?string $navigationLabel = 'Megrendelések';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Megrendelés adatok')->schema([
                Select::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name') // ha nálad nem 'name', jelezd
                    ->searchable()->preload()->required(),
                TextInput::make('order_no')->label('Rendelésszám')->required()->unique(ignoreRecord: true),
                DatePicker::make('order_date')->default(now())->required(),
                DatePicker::make('due_date')->label('Határidő'),
                Select::make('status')->options([
                    'draft' => 'Tervezet',
                    'confirmed' => 'Visszaigazolt',
                    'in_production' => 'Gyártás alatt',
                    'partial' => 'Részben kész',
                    'completed' => 'Lezárt',
                    'canceled' => 'Törölt',
                ])->default('draft'),
                TextInput::make('currency')->default('HUF')->maxLength(3),
                Textarea::make('notes')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_no')->label('Rendelésszám')->searchable()->sortable(),
            TextColumn::make('partner.name')->label('Partner')->searchable(),
            TextColumn::make('order_date')->date()->label('Dátum')->sortable(),
            TextColumn::make('due_date')->date()->label('Határidő')->sortable(),
            BadgeColumn::make('status')->colors([
                'warning' => 'draft',
                'info'    => 'confirmed',
                'primary' => 'in_production',
                'success' => 'completed',
                'danger'  => 'canceled',
                'gray'    => 'partial',
            ]),
            TextColumn::make('total_net')->money('HUF')->label('Összesen (nettó)')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options([
                'draft' => 'Tervezet', 'confirmed' => 'Visszaigazolt',
                'in_production' => 'Gyártás alatt', 'partial' => 'Részben kész',
                'completed' => 'Lezárt', 'canceled' => 'Törölt',
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            PartnerOrderResource\RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => PartnerOrderResource\Pages\ListPartnerOrders::route('/'),
            'create' => PartnerOrderResource\Pages\CreatePartnerOrder::route('/create'),
            'edit' => PartnerOrderResource\Pages\EditPartnerOrder::route('/{record}/edit'),
        ];
    }
}
