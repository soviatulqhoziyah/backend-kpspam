<?php

namespace App\Repositories;

use App\Models\User;

class AuthRepository
{
    public function findByUsername($username)
    {
        return User::where('username', $username)->first();
    }
}