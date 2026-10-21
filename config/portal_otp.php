<?php

return [
    // Independent from administrative sessions/mail flows. Default: no real mail.
    'send_enabled' => env('PORTAL_OTP_SEND_ENABLED', false),
    'key' => env('PORTAL_OTP_KEY', ''), // base64 of 32 random bytes; never APP_KEY fallback
    'allowlist' => env('PORTAL_OTP_ALLOWLIST', ''),
    'directory' => storage_path('app/private/portal-otp'),
];
