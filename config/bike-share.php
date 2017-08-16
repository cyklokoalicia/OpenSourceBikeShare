<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global system
    |--------------------------------------------------------------------------
    */

    'system' => [
        'name' => env('SYSTEM_NAME', 'BikeShare'),

        /*
        |--------------------------------------------------------------------------
        | App url
        |--------------------------------------------------------------------------
        |
        | Base url to app
        |
        */

        'url' => env('APP_URL'),

        /*
        |--------------------------------------------------------------------------
        | Center point latitude
        |--------------------------------------------------------------------------
        |
        | language code such as en, de etc.
        | translation file must be in resources/lang directory,
        | defaults to English if non-existent
        |
        */

        'system_lang' => env('SYSTEM_LOCALE', 'en'),

        /*
        |--------------------------------------------------------------------------
        | Center point latitude
        |--------------------------------------------------------------------------
        |
        | default map center point - latitude
        |
        */

        'lat' => env('SYSTEM_LAT', '48.148154'),

        /*
        |--------------------------------------------------------------------------
        | Center point longitude
        |--------------------------------------------------------------------------
        |
        | default map center point - longitude
        |
        */

        'long' => env('SYSTEM_LONG', '17.117232'),

        /*
        |--------------------------------------------------------------------------
        | MAP ZOOM
        |--------------------------------------------------------------------------
        |
        | default map zoom
        |
        */

        'zoom' => env('SYSTEM_ZOOM', 15),

        /*
        |--------------------------------------------------------------------------
        | Rules link
        |--------------------------------------------------------------------------
        |
        | rules link was send to member in confirmation email
        | system rules / help URL
        |
        */

        'rules' => env('SYSTEM_RULES', 'http://'),

        /*
        |--------------------------------------------------------------------------
        | Email
        |--------------------------------------------------------------------------
        |
        | all email will be send from this email
        | system From: email address for sending emails
        |
        */

        'email' => env('SYSTEM_EMAIL', 'foo@bar.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Watches
    |--------------------------------------------------------------------------
    */

    'watches' => [

        /*
        |--------------------------------------------------------------------------
        | Email notification
        |--------------------------------------------------------------------------
        |
        | notification email for notifications such as notes etc.
        | blank if notifications not required
        |
        */

        'email' => env('WATCHES_EMAIL', 'foo@bar.com'),

        /*
        |--------------------------------------------------------------------------
        | Stack
        |--------------------------------------------------------------------------
        |
        | false - do not watch stack,
        | true - notify if other than the top of the stack bike is rented from a stand (independent from forcestack)
        |
        */

        'stack' => env('WATCHES_STACK', false),

        /*
        |--------------------------------------------------------------------------
        | Long rental
        |--------------------------------------------------------------------------
        |
        | in hours (bike rented for more than X h)
        |
        */

        'long_rental' => env('WATCHES_LONG_RENTAL', 24),

        /*
        |--------------------------------------------------------------------------
        | Time too many
        |--------------------------------------------------------------------------
        |
        | in hours (high number of rentals by one person in a short time)
        |
        */

        'time_too_many' => env('WATCHES_TIME_TOO_MANY', 1),

        /*
        |--------------------------------------------------------------------------
        | Number to many
        |--------------------------------------------------------------------------
        |
        | if user_limit + number_too_many reached in time_too_many, then notify
        |
        */

        'number_too_many' => env('WATCHES_NUMBER_TOO_MANY', 1),

        /*
        |--------------------------------------------------------------------------
        | Free time
        |--------------------------------------------------------------------------
        |
        | in minutes (rental changes from free to paid after this time and CREDIT_RENT is deducted)
        |
        */

        'free_time' => env('WATCHES_FREE_TIME', 30),

        /*
        |--------------------------------------------------------------------------
        | Flat price cycle
        |--------------------------------------------------------------------------
        |
        | in minutes (uses flat price CREDIT_RENT every WATCHES_FLAT_PRICE_CYCLE
        | minutes after first paid period)
        |
        | i.e. WATCHES_FREE_TIME * 2
        |
        */

        'flat_price_cycle' => env('WATCHES_FLAT_PRICE_CYCLE', 60),

        /*
        |--------------------------------------------------------------------------
        | Double price cycle
        |--------------------------------------------------------------------------
        |
        | in minutes (doubles the rental price CREDIT_RENT every WATCHES_DOUBLE_PRICE_CYCLE
        | minutes after first paid period
        |
        | i.e. WATCHES_FREE_TIME * 2
        |
        */

        'double_price_cycle' => env('WATCHES_DOUBLE_PRICE_CYCLE', 60),

        /*
        |--------------------------------------------------------------------------
        | Double price cycle cap
        |--------------------------------------------------------------------------
        |
        | number of cycles after doubling of rental price CREDIT_RENT is capped and stays flat
        | (but reached cycle multiplier still applies)
        |
        */

        'double_price_cycle_cap' => env('WATCHES_DOUBLE_PRICE_CYCLE_CAP', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [

        /*
        |--------------------------------------------------------------------------
        | Limits after registration
        |--------------------------------------------------------------------------
        |
        | number of bikes user can rent after he registered:
        | 0 = no bike
        | 1 = 1 bike etc.
        |
        */

        'registration' => env('LIMITS_REGISTRATION', 0),

        /*
        |--------------------------------------------------------------------------
        | Limits increase
        |--------------------------------------------------------------------------
        |
        | allow more bike rentals in addition to user limit:
        | 0 = not allowed
        | otherwise: temporary limit increase - number of bikes
        |
        */

        'increase' => env('LIMITS_INCREASE', 0)

    ],

    /*
    |--------------------------------------------------------------------------
    | Credit system in app
    |--------------------------------------------------------------------------
    */

    'credit' => [

        /*
        |--------------------------------------------------------------------------
        | Enable credit system
        |--------------------------------------------------------------------------
        |
        | false = no credit system
        | true = apply credit system rules and deductions
        |
        */

        'enabled' => env('CREDIT_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | Default currency symbol
        |--------------------------------------------------------------------------
        |
        | currency used for credit system
        |
        */

        'currency' => env('CREDIT_CURRENCY', '€'),

        /*
        |--------------------------------------------------------------------------
        | Minimal credit
        |--------------------------------------------------------------------------
        |
        | minimum credit required to allow any bike operations
        |
        */

        'min' => env('CREDIT_MIN', 0),

        /*
        |--------------------------------------------------------------------------
        | Rent
        |--------------------------------------------------------------------------
        |
        | rental fee (after $watches["freetime"])
        |
        */

        'rent' => env('CREDIT_RENT', 0),

        /*
        |--------------------------------------------------------------------------
        | Price cycle
        |--------------------------------------------------------------------------
        |
        | 0 = disabled
        | 1 = charge flat price $credit["rent"] every $watches["flatpricecycle"] minutes
        | 2 = charge doubled price $credit["rent"] every $watches["doublepricecycle"] minutes
        |
        */

        'price_cycle' => env('CREDIT_PRICE_CYCLE', 0),

        /*
        |--------------------------------------------------------------------------
        | Long rental
        |--------------------------------------------------------------------------
        |
        | long rental fee ($watches["longrental"] time)
        |
        */

        'long_rental' => env('CREDIT_LONG_RENTAL', 5),

        /*
        |--------------------------------------------------------------------------
        | Limit increase
        |--------------------------------------------------------------------------
        |
        | credit needed to temporarily increase limit, applicable only when $limits["increase"]>0
        |
        */

        'limit_increase' => env('CREDIT_LIMIT_INCREASE', 0),

        /*
        |--------------------------------------------------------------------------
        | Violation
        |--------------------------------------------------------------------------
        |
        | credit deduction for rule violations (applied by admins)
        |
        */

        'violation' => env('CREDIT_VIOLATION', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stacking bike in stands
    |--------------------------------------------------------------------------
    |
    | false = allow renting any bike at stand
    | true = allow renting last bicycle returned only (top of stack)
    |
    */

    'stack_bike' => env('STACK_BIKE', false),

    /*
    |--------------------------------------------------------------------------
    | Watch bikes rented out of top of the stack
    |--------------------------------------------------------------------------
    | false = do not watch stack
    | true = notify if other than the top of the stack bike is rented from a stand (independent of 'stack_bike')
    |
    */

    'stack_watch' => env('STACK_WATCH', false),

    /*
    |--------------------------------------------------------------------------
    | Notification for users
    |--------------------------------------------------------------------------
    |
    | false = no notification send to users (when admins get notified)
    | true = notification messages sent to users as well
    |
    */

    'notify_user' => env('NOTIFY_USER', false),

    'sms' => [
        'enabled' => env('SMS_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | SMS Connectors / Gateways
        |--------------------------------------------------------------------------
        |
        | null, log, euroSms, twilio
        |
        */
        'connector' => env('SMS_CONNECTOR', 'log'),
    ],
];
