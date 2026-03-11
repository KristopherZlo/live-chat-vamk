<?php

namespace App\Models;

use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'registration_ip',
        'password',
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
            'is_dev' => 'boolean',
        ];
    }
    
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function emailVerificationCode()
    {
        return $this->hasOne(EmailVerificationCode::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        app(EmailVerificationCodeService::class)->send($this);
    }
}
