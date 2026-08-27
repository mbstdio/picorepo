<?php

namespace App\Http\Requests;

use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+([._-][a-z0-9]+)*$/',
                Rule::unique('repositories', 'name')->ignore($repository->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($repository): void {
                    if (Repository::where('slug', Str::slug($value))->whereKeyNot($repository)->exists()) {
                        $fail('The generated repository URL is already in use.');
                    }

                    if ($value !== $repository->name && $repository->packages()->has('versions')->exists()) {
                        $fail('Published repositories cannot be renamed because their Composer URL must remain stable.');
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
}
