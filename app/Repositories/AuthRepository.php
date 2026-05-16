<?php

namespace App\Repositories;

use App\Models\User;

class AuthRepository
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function findByUsername($username)
    {
        return $this->model->where('username', $username)->first();
    }
}