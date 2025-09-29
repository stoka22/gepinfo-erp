<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class TimeEntriesRelationManager extends RelationManager
{
    
    protected static string $relationship = 'timeEntries';
    protected static string $slug = 'time-entries';
    protected static ?string $title = 'Szabadságok / Túlórák / Táppénz';
    

    // (opcionális) egyértelműsítjük a modellt
    public function getModel(): string
    {
        return TimeEntry::class;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('employee_id')
                ->default(fn () => $this->getOwnerRecord()?->getKey())
                ->dehydrated(),
            Forms\Components\Hidden::make('requested_by')
                ->default(fn () => Auth::id())
                ->dehydrated(),

            Forms\Components\Select::make('type')->label('Típus')->options([
                TimeEntryType::Vacation->value  => 'Szabadság',
                TimeEntryType::Overtime->value  => 'Túlóra',
                TimeEntryType::SickLeave->value => 'Táppénz',
            ])->required()->live(),

            Forms\Components\DatePicker::make('start_date')->label('Kezdet')->required(),
            Forms\Components\DatePicker::make('end_date')->label('Vége')
                ->visible(fn (Forms\Get $get) => $get('type') !== TimeEntryType::Overtime->value)
                ->afterOrEqual('start_date'),

            Forms\Components\TextInput::make('hours')->label('Órák')
                ->numeric()->minValue(0.25)->step(0.25)
                ->visible(fn (Forms\Get $get) => $get('type') === TimeEntryType::Overtime->value),

            Forms\Components\Select::make('status')->label('Státusz')->options([
                TimeEntryStatus::Pending->value  => 'Függőben',
                TimeEntryStatus::Approved->value => 'Jóváhagyva',
                TimeEntryStatus::Rejected->value => 'Elutasítva',
            ])->default(TimeEntryStatus::Pending->value)->required(),

            Forms\Components\Textarea::make('note')->label('Megjegyzés')->rows(3),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            // ⬇️ LÉNYEG: explicit query, owner hiányakor "üres" lekérdezés
            ->query(function (): Builder {
                $ownerId = $this->getOwnerRecord()?->getKey();

                $q = TimeEntry::query();
                return $ownerId
                    ? $q->where('employee_id', $ownerId)->latest('start_date')
                    : $q->whereRaw('1 = 0'); // sosem ad vissza rekordot, de Builder NEM null
            })
            ->columns([
                Tables\Columns\BadgeColumn::make('type')->label('Típus')
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'vacation' => 'warning', 'overtime' => 'info', 'sick_leave' => 'danger','regular' =>'info', default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'vacation' => 'Szabadság', 'overtime' => 'Túlóra', 'sick_leave' => 'Táppénz','regular' => 'Munka', default => (string) $state,
                    }),
                Tables\Columns\TextColumn::make('start_date')->date()->label('Kezdet')->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date()->label('Vége')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('hours')->numeric(2)->label('Órák')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')->label('Státusz')
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'pending' => 'gray', 'approved' => 'success', 'rejected' => 'danger', default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'pending' => 'Függőben', 'approved' => 'Jóváhagyva', 'rejected' => 'Elutasítva', default => (string) $state,
                    }),
            ])
            ->headerActions([ Tables\Actions\CreateAction::make() ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')->label('Jóváhagy')->icon('heroicon-o-check-circle')
                    ->visible(fn (TimeEntry $r) => Auth::user()->can('approve', $r) && ($r->status->value ?? $r->status) === 'pending')
                    ->requiresConfirmation()
                    ->action(function (TimeEntry $r) {
                        $r->status = TimeEntryStatus::Approved;
                        $r->approved_by = Auth::id();
                        $r->save();
                        Notification::make()->title('Jóváhagyva')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')->label('Elutasít')->icon('heroicon-o-x-circle')
                    ->visible(fn (TimeEntry $r) => Auth::user()->can('approve', $r) && ($r->status->value ?? $r->status) === 'pending')
                    ->requiresConfirmation()
                    ->action(function (TimeEntry $r) {
                        $r->status = TimeEntryStatus::Rejected;
                        $r->approved_by = Auth::id();
                        $r->save();
                        Notification::make()->title('Elutasítva')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? null) === TimeEntryType::Overtime->value) {
            $data['end_date'] = null;
        } else {
            $data['hours'] = null;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateFormDataBeforeCreate($data);
    }
}
