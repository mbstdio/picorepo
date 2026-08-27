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
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(([._]|-{1,2})[a-z0-9]+)*$/',
                Rule::unique('packages')->where('repository_id', $package->repository_id)->ignore($package->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => strtolower($this->input('name'))]);
        }
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A package with this name already exists in this repository.',
            'name.regex' => 'The name must be a valid Composer package name.',
        ];
    }
}
