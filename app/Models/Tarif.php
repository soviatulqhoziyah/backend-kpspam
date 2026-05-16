<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarif extends Model
{
    protected $guarded = ['id'];
    public function billings()
    {
        return $this->hasMany(Billing::class);
    }
}
