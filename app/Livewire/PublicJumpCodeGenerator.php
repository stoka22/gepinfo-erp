<?php

namespace App\Livewire;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PublicJumpCodeGenerator extends Component implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    /** Filament form state */
    public array $data = [
        'key'     => null,
        'variant' => 1,
    ];

    /** Megjelenítendő eredmény */
    public ?string $code = null;

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->label('Kulcs')
                ->helperText('Csak számok – pl. 165701')
                ->prefixIcon('heroicon-o-hashtag')
                ->numeric()
                ->rule('regex:/^\d+$/')
                ->required()
                ->placeholder('165701'),

            Forms\Components\Radio::make('variant')
                ->label('Változat')
                ->inline()
                ->options([
                    1 => 'Paraméter',
                    2 => 'GPS Temp',
                    3 => 'GPS Unlock',
                ])
                ->default(1)
                ->required(),
        ])->statePath('data');
    }

    public function generate(): void
    {
        $this->validate([
            'data.key'     => ['required','regex:/^\d+$/'],
            'data.variant' => ['required','in:1,2,3'],
        ], [
            'data.key.regex' => 'A kulcs csak szám lehet.',
        ]);

        try {
            $key     = (string) $this->data['key'];
            $variant = (int) $this->data['variant'];

            // TODO: cseréld a saját szolgáltatásodra:
            // $this->code = app(\App\Services\JumpCodeService::class)->make($key, $variant);
            $this->code = substr(hash('sha256', $key.'|'.$variant), 0, 8);

            Notification::make()
                ->title('Kész! A kód legenerálva.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'data.key' => 'Hoppá, valami nem sikerült. Próbáld újra.',
            ]);
        }
    }

    public function clear(): void
    {
        $this->reset(['data', 'code']);
        $this->data = ['key' => null, 'variant' => 1];
        $this->form->fill($this->data);
    }

    public function render()
    {
        return view('livewire.public-jump-code-generator');
    }
}
