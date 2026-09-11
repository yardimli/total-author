<?php

namespace App\Support;

class Integrations
{
    public static function google(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));
    }

    public static function mail(): bool
    {
        return filled(config('services.mailgun.domain')) && filled(config('services.mailgun.secret'));
    }
}
