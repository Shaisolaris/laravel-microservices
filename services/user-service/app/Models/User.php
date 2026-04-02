<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'status'];
    protected $hidden = ['password'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];
}
