<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Revision extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array'];
}
