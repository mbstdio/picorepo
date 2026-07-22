<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $package = $this->route('package');

        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/i', Rule::unique('packages', 'name')->ignore($package->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A package with this name already exists globally.',
            'name.regex' => 'The name may only contain letters, numbers, hyphens and underscores.',
        ];
    }
}
