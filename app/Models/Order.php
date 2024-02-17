<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable =[
        'orderid',
        'paykeeper_id',
        'account_id',
        'referer',
        'sum',
        'payed',
        'cart'
    ];
}
