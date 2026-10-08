<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use stdClass;

class ComposerMetadata implements ValidationRule
{
    public const MAX_BYTES = 16384;

    public const SUPPORTED_KEYS = [
        'license', 'homepage', 'keywords', 'authors', 'support', 'funding',
        'require', 'require-dev', 'conflict', 'replace', 'provide', 'suggest',
        'autoload', 'autoload-dev', 'extra', 'bin', 'time', 'abandoned',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > self::MAX_BYTES) {
            $fail('Custom Composer metadata must not exceed 16 KiB.');

            return;
        }

        $metadata = is_string($value) ? json_decode($value) : null;

        if (! ($metadata instanceof stdClass)) {
            $fail('Custom Composer metadata must be a JSON object.');

            return;
        }

        if (array_diff(array_keys(get_object_vars($metadata)), self::SUPPORTED_KEYS)) {
            $fail('Custom Composer metadata contains unsupported or protected keys.');
        }
    }
}
