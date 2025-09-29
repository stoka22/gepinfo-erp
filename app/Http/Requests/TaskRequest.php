<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Közvetlenül a validáláshoz használt input tömböt adjuk vissza,
     * itt mapeljük a resource_id -> machine_id mezőt és állítjuk az alapértékeket.
     */
    public function validationData(): array
    {
        $data = parent::validationData();

        // resource_id -> machine_id (ha a front még resource_id-t küld)
        if (array_key_exists('resource_id', $data) && !array_key_exists('machine_id', $data)) {
            $data['machine_id'] = $data['resource_id'];
            unset($data['resource_id']);
        }

        // setup_minutes alapértelmezés + egészre cast
        $data['setup_minutes'] = isset($data['setup_minutes']) && $data['setup_minutes'] !== ''
            ? (int) $data['setup_minutes']
            : 0;

        return $data;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required','string','max:255'],
            // Ha NINCS még machines tábla, ideiglenesen használd csak: ['required','integer']
            'machine_id'    => ['required','integer','exists:machines,id'],
            'starts_at'     => ['required','date'],
            'ends_at'       => ['required','date','after:starts_at'],
            'setup_minutes' => ['nullable','integer','min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'A feladat neve kötelező.',
            'machine_id.required' => 'Válassz gépet (machine_id)!',
            'machine_id.exists'   => 'A kiválasztott gép nem létezik.',
            'ends_at.after'       => 'A befejezési időnek a kezdés után kell lennie.',
        ];
    }
}
