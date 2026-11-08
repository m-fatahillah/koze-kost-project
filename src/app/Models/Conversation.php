<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'seeker_id',
        'owner_id',
        'property_id',
        'last_message_id',
        'seeker_unread_count',
        'owner_unread_count',
    ];

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function otherUser(User $user): User
    {
        return $this->seeker_id === $user->id ? $this->owner : $this->seeker;
    }

    public function unreadCountFor(User $user): int
    {
        return $this->seeker_id === $user->id
            ? $this->seeker_unread_count
            : $this->owner_unread_count;
    }

    public function markReadFor(User $user): void
    {
        if ($this->seeker_id === $user->id) {
            $this->seeker_unread_count = 0;
        } else {
            $this->owner_unread_count = 0;
        }
        $this->save();
    }

    public function incrementUnreadFor(User $user): void
    {
        if ($this->seeker_id === $user->id) {
            $this->owner_unread_count += 1;
        } else {
            $this->seeker_unread_count += 1;
        }
        $this->save();
    }
}
