<?php

// app/Http/Requests/StoreTaskDependencyRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskDependencyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'predecessor_id' => ['required','integer','exists:tasks,id','different:successor_id'],
            'successor_id'   => ['required','integer','exists:tasks,id'],
            'type'           => ['in:FS'],
            'lag_minutes'    => ['integer'],
        ];
    }
}
