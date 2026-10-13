<?php

return [
    'email' => env('OWNER_EMAIL', 'owner@gothamcoffee.id'),
    'password' => env('OWNER_PASSWORD', env('APP_ENV') === 'local' ? 'password123' : null),
];
