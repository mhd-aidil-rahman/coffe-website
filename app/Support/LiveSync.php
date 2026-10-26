<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LiveSync
{
    public function revision(): string
    {
        return Cache::get('live-sync:revision', 'initial');
    }

    public function changed(): void
    {
        Cache::forever('live-sync:revision', (string) Str::uuid());
    }
}
