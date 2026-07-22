<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/i', 'unique:repositories,name'],
            'type'        => ['required', Rule::in(['public', 'private'])],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The name may only contain letters, numbers, hyphens and underscores.',
        ];
    }
}
