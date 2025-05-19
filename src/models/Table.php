<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $fillable = [
        'number',
        'status',
        'capacity'
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getCurrentOrder()
    {
        return $this->orders()->where('status', 'active')->first();
    }

    public function isOccupied()
    {
        return $this->getCurrentOrder() !== null;
    }
}
