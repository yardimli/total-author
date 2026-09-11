<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCall extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['request_payload' => 'array'];
}
