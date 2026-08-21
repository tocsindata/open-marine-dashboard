<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local email verification
    |--------------------------------------------------------------------------
    |
    | Local development normally has no external SMTP service. When this flag
    | is true and APP_ENV is exactly "local", newly registered users are marked
    | verified without sending mail. This setting is ignored in every other
    | environment, including production and testing.
    |
    */
    'local_auto_verify_email' => (bool) env('MARINE_LOCAL_AUTO_VERIFY_EMAIL', false),
];
