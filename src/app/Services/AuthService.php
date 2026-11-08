<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Handle the registration logic.
     */
    public function register(array $data): User
    {
        $userAttrs = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ];

        if (isset($data['google_id'])) {
            $userAttrs['google_id'] = $data['google_id'];
        }
        
        $user = User::create($userAttrs);
        
        if (isset($data['email_verified_at'])) {
            $user->email_verified_at = $data['email_verified_at'];
            $user->save();
        }

        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        Auth::login($user);

        return $user;
    }

    /**
     * Handle the login logic.
     */
    public function login(array $credentials): void
    {
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        session()->regenerate();
    }

    /**
     * Handle the logout logic.
     */
    public function logout(): void
    {
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();
    }
}
