<?php

declare(strict_types=1);

namespace App\Models;

use Agus\Database\Model;

class User extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = ['name', 'email', 'password', 'role'];

    // Never send the password hash to a view or API response.
    protected static array $hidden = ['password'];

    public static function findByEmail(string $email): ?self
    {
        return static::where('email', strtolower(trim($email)))->first();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
