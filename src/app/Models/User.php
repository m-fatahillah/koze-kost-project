<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'phone',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Override default password reset notification to use the custom template.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\CustomResetPasswordNotification($token));
    }

    /**
     * Override default email verification notification to use the custom template.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\CustomVerifyEmailNotification);
    }

    public function savedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'user_saved_properties')->withTimestamps();
    }

    public function conversationsAsSeeker()
    {
        return $this->hasMany(Conversation::class, 'seeker_id');
    }

    public function conversationsAsOwner()
    {
        return $this->hasMany(Conversation::class, 'owner_id');
    }

    public function allConversations()
    {
        return $this->conversationsAsSeeker->merge($this->conversationsAsOwner);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->diffInMinutes(now()) < 2;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        return str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')
            ? $this->avatar
            : \Illuminate\Support\Facades\Storage::url($this->avatar);
    }
}
