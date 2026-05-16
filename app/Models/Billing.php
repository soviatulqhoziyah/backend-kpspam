<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    protected $guarded = ['id'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function tarif()
    {
        return $this->belongsTo(Tarif::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
