<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['document' => 'array', 'metadata' => 'array', 'codex_types' => 'array', 'archived' => 'boolean'];

    public function getWordCountAttribute(): int
    {
        return preg_match_all('/\S+/u', \App\Services\Manuscript::text($this->document ?? []));
    }

    public function entries()
    {
        return $this->hasMany(CodexEntry::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function proposals()
    {
        return $this->hasMany(AiProposal::class);
    }

    public function revisions()
    {
        return $this->hasMany(Revision::class);
    }
}
