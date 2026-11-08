<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ConversationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_id' => ['required', 'exists:users,id'],
            'property_id' => ['nullable', 'exists:properties,id'],
        ]);

        $user = $request->user();
        $ownerId = $validated['owner_id'];
        $propertyId = $validated['property_id'] ?? null;

        if ($user->id === $ownerId) {
            return Redirect::back()->with('error', 'You cannot chat with yourself.');
        }

        $conversation = Conversation::where('seeker_id', $user->id)
            ->where('owner_id', $ownerId)
            ->when($propertyId, function ($query) use ($propertyId) {
                $query->where('property_id', $propertyId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'seeker_id' => $user->id,
                'owner_id' => $ownerId,
                'property_id' => $propertyId,
            ]);
        }

        return Redirect::route('messages.index', ['conversation' => $conversation->id]);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $conversation->markReadFor($request->user());

        return response()->noContent();
    }
}
