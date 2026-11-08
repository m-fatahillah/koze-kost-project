<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Display the login view.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $this->authService->login($request->only('email', 'password'));

        $redirectRoute = auth()->user()->hasRole('Seeker') ? route('home', absolute: false) : route('dashboard', absolute: false);
        return redirect()->intended($redirectRoute);
    }

    /**
     * Display the registration view.
     */
    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register', [
            'googleData' => session('google_auth'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (session()->has('google_auth') && session('google_auth.email') === $data['email']) {
            $data['google_id'] = session('google_auth.google_id');
            $data['email_verified_at'] = now();
        }

        $user = $this->authService->register($data);
        
        session()->forget('google_auth');

        $redirectRoute = $user->hasRole('Seeker') ? route('home', absolute: false) : route('dashboard', absolute: false);
        return redirect($redirectRoute);
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        return redirect('/');
    }
}
