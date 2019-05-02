<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Beverage extends Model
{
    protected $hidden = [
        'created_at',
        'updated_at',
        'sku'
    ];
}
