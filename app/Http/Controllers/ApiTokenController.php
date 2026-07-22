<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
class ApiTokenController extends Controller
{
    public function index(): Response
    {
        $tokens = auth()->user()->tokens()->latest()->get()->map(fn ($t) => [
            'id'         => $t->id,
            'name'       => $t->name,
            'last_used'  => $t->last_used_at,
            'created_at' => $t->created_at,
        ]);

        return Inertia::render('Profile/ApiTokens', [
            'tokens' => $tokens,
        ]);
    }

    public function store(): RedirectResponse
    {
        request()->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $token = auth()->user()->createToken(
            request('name'),
            ['composer:read']
        );

        return redirect()->route('api-tokens.index')
            ->with('token', $token->plainTextToken)
            ->with('success', 'Token created successfully.');
    }

    public function destroy(int $tokenId): RedirectResponse
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();

        return redirect()->route('api-tokens.index')
            ->with('success', 'Token deleted.');
    }
}
