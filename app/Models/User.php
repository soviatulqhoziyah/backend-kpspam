<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    /** @use HasFactory<UserFactory> */

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $fillable = [
        'username',
        'password',
        'role',
        'status',
        'alamat',
        'noTelepon',
        'namaLengkap'
    ];


    public function billings()
    {
        return $this->hasMany(Billing::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
