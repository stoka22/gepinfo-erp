<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles; // ⬅️ FONTOS: add hozzá a HasRoles-t

    protected $guard_name = 'web'; // Spatie guard

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
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
            'password' => 'hashed', // Laravel 11 auto-hash
        ];
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function isAdmin(): bool
    {
        return ($this->role ?? null) === 'admin';
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'account_user_id');
    }

    public function employeesCreated()
    {
        return $this->hasMany(Employee::class, 'created_by_user_id');
    }
}
