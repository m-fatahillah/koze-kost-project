<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class MessageController extends Controller
{
    public function index(Request $request, ?Conversation $conversation = null)
    {
        $user = $request->user();

        $conversations = Conversation::with(['seeker', 'owner', 'property', 'lastMessage'])
            ->where(function ($query) use ($user) {
                $query->where('seeker_id', $user->id)
                    ->orWhere('owner_id', $user->id);
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($conv) use ($user) {
                $otherUser = $conv->otherUser($user);

                return [
                    'id' => $conv->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                        'is_online' => $otherUser->isOnline(),
                    ],
                    'property' => $conv->property ? [
                        'id' => $conv->property->id,
                        'title' => $conv->property->name,
                    ] : null,
                    'my_role' => $conv->seeker_id === $user->id ? 'seeker' : 'owner',
                    'last_message' => $conv->lastMessage ? [
                        'id' => $conv->lastMessage->id,
                        'content' => $conv->lastMessage->content,
                        'created_at' => $conv->lastMessage->created_at->toISOString(),
                        'sender_id' => $conv->lastMessage->sender_id,
                        'is_read' => $conv->lastMessage->is_read,
                    ] : null,
                    'unread_count' => $conv->unreadCountFor($user),
                    'updated_at' => $conv->updated_at->toISOString(),
                ];
            });

        $activeConversation = null;
        $messages = [];

        if ($conversation && $conversation->exists) {
            $isParticipant = $conversation->seeker_id === $user->id || $conversation->owner_id === $user->id;

            if ($isParticipant) {
                $conversation->markReadFor($user);

                $otherUser = $conversation->otherUser($user);

                $activeConversation = [
                    'id' => $conversation->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                        'is_online' => $otherUser->isOnline(),
                    ],
                    'property' => $conversation->property ? [
                        'id' => $conversation->property->id,
                        'title' => $conversation->property->name,
                    ] : null,
                    'my_role' => $conversation->seeker_id === $user->id ? 'seeker' : 'owner',
                ];

                $messages = $conversation->messages()
                    ->with('sender')
                    ->orderBy('created_at', 'asc')
                    ->limit(50)
                    ->get()
                    ->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'sender_id' => $message->sender_id,
                            'content' => $message->content,
                            'created_at' => $message->created_at->toISOString(),
                            'is_read' => $message->is_read,
                        ];
                    });
            }
        }

        return Inertia::render('Messages', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        $isParticipant = $conversation->seeker_id === $user->id || $conversation->owner_id === $user->id;

        if (!$isParticipant) {
            return Redirect::back()->with('error', 'You are not a participant in this conversation.');
        }

        $receiverId = $conversation->seeker_id === $user->id
            ? $conversation->owner_id
            : $conversation->seeker_id;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'content' => $validated['content'],
        ]);

        $conversation->last_message_id = $message->id;
        $conversation->incrementUnreadFor($user);
        $conversation->touch();

        return Redirect::route('messages.index', ['conversation' => $conversation->id]);
    }
}
