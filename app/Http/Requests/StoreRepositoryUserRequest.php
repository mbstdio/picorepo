<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $repository = $this->route('repository');

        return [
            'email' => [
                'required',
                'email',
                'exists:users,email',
                // Cannot invite yourself or someone already in the repo
                function ($attribute, $value, $fail) use ($repository) {
                    $user = \App\Models\User::where('email', $value)->first();
                    if ($user && $user->id === auth()->id()) {
                        $fail('You cannot invite yourself.');
                    }
                    if ($user && $repository->users()->where('user_id', $user->id)->exists()) {
                        $fail('This user already has access to this repository.');
                    }
                },
            ],
            'role'  => ['required', Rule::in(['owner', 'maintainer'])],
        ];
    }
}
