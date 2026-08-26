<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $package = $this->route('package');

        return [
            'version' => [
                'required',
                'string',
                'max:50',
                'regex:/^(v?\d+\.\d+(\.\d+)?(-[a-zA-Z0-9.]+)?|dev-.+)$/',
                Rule::unique('package_versions', 'version')->where('package_id', $package->id),
            ],
            'type' => ['required', Rule::in([
                'library',
                'project',
                'metapackage',
                'composer-plugin',
                'wordpress-plugin',
                'wordpress-theme',
            ])],
            'disk' => ['required', Rule::in(self::availableDisks())],
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:102400'], // 100MB
            'description' => ['nullable', 'string', 'max:500'],
            'extra' => ['nullable', 'json'],
        ];
    }

    public static function availableDisks(): array
    {
        $disks = config('filesystems.disks');

        return array_values(array_filter(['local', 's3'], function (string $disk) use ($disks): bool {
            $config = $disks[$disk] ?? [];

            return match ($disk) {
                'local' => ($config['driver'] ?? null) === 'local' && filled($config['root'] ?? null),
                's3' => ($config['driver'] ?? null) === 's3'
                    && filled($config['key'] ?? null)
                    && filled($config['secret'] ?? null)
                    && filled($config['region'] ?? null)
                    && filled($config['bucket'] ?? null)
                    && class_exists('League\\Flysystem\\AwsS3V3\\PortableVisibilityConverter'),
            };
        }));
    }

    public function messages(): array
    {
        return [
            'version.regex' => 'Version must be a valid Composer version (e.g. 1.0.0, v2.1.0-beta, dev-main).',
            'version.unique' => 'This version already exists for this package.',
        ];
    }
}
