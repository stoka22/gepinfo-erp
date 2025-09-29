<?php
// app/Filament/Resources/PartnerResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Filament\Resources\PartnerResource\RelationManagers\LocationsRelationManager;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
//use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use Filament\Facades\Filament;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Törzsadatok';
    protected static ?string $navigationLabel = 'Partnerek';
    protected static ?string $pluralLabel = 'Partnerek';
    protected static ?string $modelLabel = 'Partner';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alapadatok')->schema([
                Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(255),
                Forms\Components\TextInput::make('tax_id')->label('Adószám')->maxLength(64),
            ])->columns(2),

            Forms\Components\Section::make('Típusok')->schema([
                Forms\Components\Toggle::make('is_supplier')->label('Beszállító'),
                Forms\Components\Toggle::make('is_customer')->label('Vevő')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Tulajdonos cég')->schema([
                Forms\Components\Select::make('owner_company_id')
                    ->label('Létrehozó cég')
                    ->relationship(name: 'ownerCompany', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Admin láthatja/állíthatja. Mentéskor a saját cég lesz beállítva, ha nem admin.')
                    ->visible(fn()=> Filament::auth()->user()?->isAdmin() ?? false), // <-- ITT
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        //$user = Auth::user();
        $user = Filament::auth()->user(); // <-- ITT

        return $table
            ->columns([
                TextColumn::make('name')->label('Név')->searchable()->sortable(),
                TextColumn::make('tax_id')->label('Adószám')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_supplier')->label('Beszállító')->boolean(),
                IconColumn::make('is_customer')->label('Vevő')->boolean(),
                TextColumn::make('ownerCompany.name')
                    ->label('Tulaj cég')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('companies_count')
                    ->counts('companies')
                    ->label('Hozzárendelt cégek')
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_supplier')->label('Beszállító'),
                Tables\Filters\TernaryFilter::make('is_customer')->label('Vevő'),

                // Admin: „mind / csoport 1..3” szűrő
                Tables\Filters\SelectFilter::make('company_group')
                    ->label('Cégcsoport')
                    ->options([
                        'all' => 'Mind',
                        '1'   => 'Felhasználói csoport 1',
                        '2'   => 'Felhasználói csoport 2',
                        '3'   => 'Felhasználói csoport 3',
                    ])
                    ->visible(fn()=> $user?->isAdmin() ?? false)
                    ->query(function (Builder $q, array $data) {
                        $val = $data['value'] ?? null;
                        if (!$val || $val === 'all') return $q;
                        // partner akkor jelenjen meg, ha bármelyik hozzá rendelt cég a kiválasztott csoportban van
                        return $q->whereHas('companies', fn($qq)=>$qq->where('group', (int)$val));
                    }),

                // Normál felhasználónál segéd-szűrő: csak a saját cég partnerei vs mind, ami megosztott (ha van erre jog)
                Tables\Filters\SelectFilter::make('visibility')->label('Láthatóság')
                    ->options([
                        'mine'   => 'Saját cég partnerei',
                        'shared' => 'Megosztott (nem a saját cég tulaj)',
                    ])
                    ->visible(fn()=> !($user?->isAdmin()))
                    ->query(function (Builder $q, array $data) use ($user) {
                        if (! $user?->company) return $q->whereRaw('1=0');
                        return match ($data['value'] ?? 'mine') {
                            'shared' => $q->where(function($qq) use ($user){
                                $qq->whereHas('companies', fn($q2)=>$q2->where('companies.id', $user->company_id))
                                   ->where(function($q3) use ($user) {
                                       $q3->whereNull('owner_company_id')
                                          ->orWhere('owner_company_id','<>',$user->company_id);
                                   });
                            }),
                            default => $q->whereHas('companies', fn($q2)=>$q2->where('companies.id', $user->company_id)),
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /** Lista megjelenítési jogosultság + szűrés */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user(); // <-- ITT

        if ($user?->isAdmin()) {
            return $query; // admin mindent lát
        }

        // normál user: csak a saját céghez rendelt partnerek
        if ($user?->company) {
            return $query->whereHas('companies', fn($q)=>$q->where('companies.id', $user->company_id));
        }

        // ha valamiért nincs cég, ne lásson semmit
        return $query->whereRaw('1=0');
    }

    public static function getRelations(): array
    {
        return [
            LocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'edit'   => Pages\EditPartner::route('/{record}/edit'),
            'view'   => Pages\ViewPartner::route('/{record}'),
        ];
    }
}
