<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $repository = $this->route('repository');

        return [
            'name'        => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/i', Rule::unique('repositories', 'name')->ignore($repository->id)],
            'type'        => ['required', Rule::in(['public', 'private'])],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
