<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class FieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module-name' => [
                'required',
                'string',
            ],
        ];
    }
}
