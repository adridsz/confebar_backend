<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'stock',
        'category'
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
