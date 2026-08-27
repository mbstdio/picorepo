<?php

namespace App\Http\Requests;

use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+([._-][a-z0-9]+)*$/',
                'unique:repositories,name',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Repository::where('slug', Str::slug($value))->exists()) {
                        $fail('The generated repository URL is already in use.');
                    }
                },
            ],
            'type' => ['required', Rule::in(['public', 'private'])],
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
            'name.regex' => 'The name must be a valid Composer vendor name.',
        ];
    }
}
