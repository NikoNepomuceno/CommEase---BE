<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_initial',
        'last_name',
        'program',
        'email',
        'user_email_id',
        'password',
        'role',
        'otp',
        'otp_expires_at',
        'email_verified_at',
        'profile_picture',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_expires_at',
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
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    /**
     * Get the user's role.
     *
     * @return string
     */
    public function getRoleAttribute()
    {
        return $this->attributes['role'];
    }

    public function organizedEvents()
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function volunteeredEvents()
    {
        return $this->belongsToMany(Event::class, 'event_volunteers', 'user_id', 'event_id')
            ->withPivot(['things_brought', 'time_in', 'time_out', 'attendance_status', 'attendance_notes', 'attendance_marked_at'])
            ->withTimestamps()
            ->withCasts([
                'things_brought' => 'array',
                'time_in' => 'datetime',
                'time_out' => 'datetime',
                'attendance_marked_at' => 'datetime'
            ]);
    }

    public function suggestions()
    {
        return $this->hasMany(Suggestion::class, 'volunteer_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function getFullNameAttribute()
    {
        return $this->middle_initial
            ? "{$this->first_name} {$this->middle_initial}. {$this->last_name}"
            : "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the events that the user is volunteering for.
     */
    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_volunteers', 'user_id', 'event_id')
            ->withPivot(['things_brought', 'time_in', 'time_out', 'attendance_status', 'attendance_notes', 'attendance_marked_at'])
            ->withTimestamps();
    }



    // Add this method to automatically set user_email_id when email is set
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = $value;
        $this->attributes['user_email_id'] = strstr($value, '@', true);
    }
}
