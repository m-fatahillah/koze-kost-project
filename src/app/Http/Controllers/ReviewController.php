<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, Property $property)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($property->owner_id === $user->id) {
            return redirect()->back()->withErrors([
                'error' => 'Pemilik tidak dapat memberi ulasan pada propertinya sendiri.',
            ]);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        Review::updateOrCreate(
            [
                'property_id' => $property->id,
                'seeker_id' => $user->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Ulasan berhasil dikirim.');
    }

    public function destroy(Review $review)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($review->seeker_id !== $user->id && !$user->hasRole('Admin')) {
            abort(403);
        }

        $review->delete();

        return redirect()->back()->with('success', 'Ulasan berhasil dihapus.');
    }
}
