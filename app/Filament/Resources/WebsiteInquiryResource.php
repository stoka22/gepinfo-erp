<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebsiteInquiryResource\Pages;
use App\Models\WebsiteInquiry;
use App\Support\WebsiteInquiryQuestionnaire;
use Filament\Forms;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;

class WebsiteInquiryResource extends Resource
{
    protected static ?string $model = WebsiteInquiry::class;

    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Honlap';
    protected static ?string $navigationLabel = 'Weboldal igényfelmérők';
    protected static ?string $modelLabel      = 'Weboldal igényfelmérő';
    protected static ?string $pluralLabel     = 'Weboldal igényfelmérők';

    public static function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function canCreate(): bool
    {
        return false; // csak a publikus űrlap tölti fel
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Beküldve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cegnev')
                    ->label('Cégnév')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('kapcsolattarto_neve')
                    ->label('Kapcsolattartó')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('telefon')
                    ->label('Telefon')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('')->tooltip('Részletek'),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Törlés'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        $schema = [
            Forms\Components\Placeholder::make('created_at')
                ->label('Beküldve')
                ->content(fn (WebsiteInquiry $record) => $record->created_at?->format('Y-m-d H:i:s')),
        ];

        foreach (WebsiteInquiryQuestionnaire::sections() as $section) {
            $schema[] = Forms\Components\Section::make($section['title'])
                ->schema(
                    collect($section['fields'])
                        ->map(fn (array $field) => Forms\Components\Placeholder::make($field['key'])
                            ->label($field['label'])
                            ->content(fn (WebsiteInquiry $record) => static::formatAnswer($field, $record)))
                        ->all()
                )
                ->collapsible();
        }

        return $form->schema($schema);
    }

    /** A rekord egy mezőjének emberi olvasásra formázott értéke (checkbox: felsorolás, radio: felirat). */
    protected static function formatAnswer(array $field, WebsiteInquiry $record): string
    {
        $value = $field['key'] === 'cegnev'
            ? $record->cegnev
            : ($field['key'] === 'kapcsolattarto_neve'
                ? $record->kapcsolattarto_neve
                : ($field['key'] === 'telefon'
                    ? $record->telefon
                    : ($field['key'] === 'email'
                        ? $record->email
                        : ($record->answers[$field['key']] ?? null))));

        if (blank($value)) {
            return '—';
        }

        if ($field['type'] === 'checkbox' && is_array($value)) {
            return collect($value)
                ->map(fn ($v) => $field['options'][$v] ?? $v)
                ->join(', ');
        }

        if ($field['type'] === 'radio') {
            return $field['options'][$value] ?? $value;
        }

        return (string) $value;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsiteInquiries::route('/'),
            'view'  => Pages\ViewWebsiteInquiry::route('/{record}'),
        ];
    }
}
