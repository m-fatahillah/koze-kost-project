<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\UploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProfileController extends Controller
{
    use UploadTrait;

    /**
     * Display the user's profile page.
     */
    public function show()
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            $user->avatar = Storage::url($user->avatar);
        }

        return Inertia::render('Profile/Index', [
            'user' => $user,
            'isGoogleAccount' => !is_null($user->google_id),
        ]);
    }

    /**
     * Update the user's basic profile information (name, avatar).
     */
    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'avatar' => 'nullable|image|max:2048',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Remove old avatar if exists
            if ($user->avatar && $this->exist($user->avatar)) {
                $this->remove($user->avatar);
            }
            // Upload new avatar
            $validated['avatar'] = $this->upload('avatars', $request->file('avatar'));
        } else {
            unset($validated['avatar']);
        }

        $user->update($validated);

        return redirect()->back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Update the user's password (only for local accounts).
     */
    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Prevent password change for Google-only accounts
        if (!is_null($user->google_id) && is_null($user->password)) {
            return redirect()->back()->with('error', 'Anda tidak dapat mengubah password untuk akun yang terhubung dengan Google.');
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', 'Password berhasil diubah.');
    }
}
