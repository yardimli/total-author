<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    protected function transform($key, $value)
    {
        // Whitespace inside prose is authored content, including around inline emphasis nodes.
        if (str_starts_with($key, 'document.') || in_array($key, ['content', 'message', 'selection.text'])) {
            return $value;
        }

        return parent::transform($key, $value);
    }

    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
