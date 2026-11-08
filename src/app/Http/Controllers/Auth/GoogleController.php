<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Find existing user by google_id or email
            $user = User::where('google_id', $googleUser->id)
                        ->orWhere('email', $googleUser->email)
                        ->first();

            if ($user) {
                // If user exists but doesn't have google_id, update it
                $updates = [];
                if (!$user->google_id) {
                    $updates['google_id'] = $googleUser->id;
                }
                // Verify email if it was previously unverified (e.g. manual fallback)
                if (is_null($user->email_verified_at)) {
                    $updates['email_verified_at'] = now();
                }

                if (!empty($updates)) {
                    $user->update($updates);
                }
            } else {
                // Store in session and redirect to register to choose role
                session([
                    'google_auth' => [
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                    ]
                ]);

                return redirect()->route('register');
            }

            Auth::login($user);

            $redirectRoute = $user->hasRole('Seeker') ? route('home', absolute: false) : route('dashboard', absolute: false);
            return redirect()->intended($redirectRoute);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google Auth Error: ' . $e->getMessage());
            return redirect('/login')->with('error', 'Gagal login via Google. Silakan coba lagi. Error: ' . $e->getMessage());
        }
    }
}
