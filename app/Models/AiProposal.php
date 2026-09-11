<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProposal extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['changes' => 'array', 'decisions' => 'array', 'chat_message_id' => 'integer'];
}
