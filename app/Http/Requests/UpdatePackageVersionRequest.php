<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $package = $this->route('package');
        $version = $this->route('version');

        return [
            'version' => [
                'required',
                'string',
                'max:50',
                'regex:/^(v?\d+\.\d+(\.\d+)?(-[a-zA-Z0-9.]+)?|dev-.+)$/',
                Rule::unique('package_versions', 'version')
                    ->where('package_id', $package->id)
                    ->ignore($version->id),
            ],
            'type' => ['required', Rule::in([
                'library',
                'project',
                'metapackage',
                'composer-plugin',
                'wordpress-plugin',
                'wordpress-theme',
            ])],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'version.regex' => 'Version must be a valid Composer version (e.g. 1.0.0, v2.1.0-beta, dev-main).',
            'version.unique' => 'This version already exists for this package.',
        ];
    }
}
