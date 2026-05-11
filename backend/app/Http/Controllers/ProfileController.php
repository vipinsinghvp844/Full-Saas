<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return $this->jsonResponse(['message' => 'Unauthenticated.'], 401, $request);
        }

        return $this->jsonResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'email_verified_at' => $user->email_verified_at,
            ],
        ], 200, $request);
    }
}
