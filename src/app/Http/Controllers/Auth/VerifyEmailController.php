<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyEmailController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function show(Request $request): RedirectResponse|Response
    {
        $redirectRoute = $request->user()->hasRole('Seeker') ? route('home', absolute: false) : route('dashboard', absolute: false);
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended($redirectRoute)
                    : Inertia::render('Auth/VerifyEmail', ['status' => session('status')]);
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $redirectRoute = $request->user()->hasRole('Seeker') ? route('home', absolute: false) : route('dashboard', absolute: false);
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($redirectRoute.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($request->user()));
        }

        return redirect()->intended($redirectRoute.'?verified=1');
    }

    /**
     * Send a new email verification notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            $redirectRoute = $request->user()->hasRole('Seeker') ? route('home', absolute: false) : route('dashboard', absolute: false);
            return redirect()->intended($redirectRoute);
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Kami telah mengirimkan tautan verifikasi ke email Anda!');
    }
}
