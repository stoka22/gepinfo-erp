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

    protected static function currentUser(): ?\App\Models\User
    {
        return \Filament\Facades\Filament::auth()->user();
    }

    public static function form(Form $form): Form
    {
        $admin = (Filament::auth()->user()?->isAdmin()) ?? false;

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
                    ->visible($admin),

                // NEM ADMIN – csak nézet
                Forms\Components\Placeholder::make('owner_company_name')
                    ->label('Tulajdonos cég')
                    ->content(function (?Partner $record) {
                        // 1) meglévő rekordhoz az ownerCompany nevét
                        if ($record?->ownerCompany) return $record->ownerCompany->name;

                        // 2) új rekordnál a bejelentkezett user cégének neve
                        $u = Filament::auth()->user();
                        return $u?->company?->name ?? '—';
                    })
                    ->visible(! $admin),

                // NEM ADMIN – rejtett mező, hogy mentődjön az ID
                Forms\Components\Hidden::make('owner_company_id')
                    ->default(fn () => Filament::auth()->user()?->company_id)
                    ->dehydrated(! $admin),
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

                // ← itt már a query tölti fel: withCount('companies')
          /*      TextColumn::make('companies_count')
                    ->label('Hozzárendelt cégek')
                    ->sortable(),*/
            ])
           ->filters([
                Tables\Filters\TernaryFilter::make('is_supplier')->label('Beszállító'),
                Tables\Filters\TernaryFilter::make('is_customer')->label('Vevő'),

                Tables\Filters\SelectFilter::make('company_group')
                    ->label('Cégcsoport')
                    ->options([
                        'all' => 'Mind',
                        '1'   => 'Felhasználói csoport 1',
                        '2'   => 'Felhasználói csoport 2',
                        '3'   => 'Felhasználói csoport 3',
                    ])
                    ->visible(fn () => (bool) static::currentUser()?->isAdmin())
                    ->query(function (Builder $q, array $data) {
                        $val = $data['value'] ?? null;
                        if (!$val || $val === 'all') return $q;
                        return $q->whereExists(function ($sub) use ($val) {
                            $sub->selectRaw('1')
                                ->from('company_partner as cp')
                                ->join('companies as c', 'c.id', '=', 'cp.company_id')
                                ->whereColumn('cp.partner_id', 'partners.id')
                                ->where('c.group', (int) $val);
                        });
                    }),

                Tables\Filters\SelectFilter::make('visibility')
                    ->label('Láthatóság')
                    ->options([
                        'mine'   => 'Saját cég partnerei',
                        'shared' => 'Megosztott (nem a saját cég tulaj)',
                    ])
                    ->visible(fn () => ! (static::currentUser()?->isAdmin()))
                    ->query(function (Builder $q, array $data) {
                        $user = static::currentUser();
                        $companyId = $user?->company_id;
                        if (!$companyId) return $q->whereRaw('1=0');

                        $mode = $data['value'] ?? 'mine';

                        $existsMine = function ($sub) use ($companyId) {
                            $sub->selectRaw('1')
                                ->from('company_partner as cp')
                                ->whereColumn('cp.partner_id', 'partners.id')
                                ->where('cp.company_id', $companyId);
                        };
                        
                        if ($mode === 'shared') {
                            return $q->whereExists($existsMine)
                                    ->where(function ($qq) use ($companyId) {
                                        $qq->whereNull('owner_company_id')
                                            ->orWhere('owner_company_id', '<>', $companyId);
                                    });
                        }

                        // mine
                        return $q->whereExists($existsMine);
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
        $q = parent::getEloquentQuery()
            ->select('*') // biztosítsuk, hogy Eloquent marad
            ->withCount('companies');

        $user = static::currentUser();

        if ($user?->isAdmin()) {
            return $q;
        }

        if ($user?->company_id) {
            $companyId = $user->company_id;

            // company_partner = pivot tábla (ha más a neve, írd át!)
            return $q->whereExists(function ($sub) use ($companyId) {
                $sub->selectRaw('1')
                    ->from('company_partner as cp')
                    ->whereColumn('cp.partner_id', 'partners.id')
                    ->where('cp.company_id', $companyId);
            });
        }

        return $q->whereRaw('1=0');
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
