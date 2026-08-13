<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Billable;
use SubKit\Concerns\HasCapabilities;

class User extends Authenticatable
{
    use Billable, HasCapabilities, HasFactory;

    protected $guarded = [];
}
