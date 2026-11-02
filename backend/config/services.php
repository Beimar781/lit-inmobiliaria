<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    // Correo en producción (Railway bloquea SMTP): ver app/Support/BrevoTransport.php
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

    // Pago con tarjeta (CU28) con Stripe Checkout. Llaves SOLO en el .env (nunca en el repositorio): ver .env.example
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Cobrar también en bolivianos (BOB) con tarjeta. Apagado hasta comprobar que la cuenta de Stripe acepta BOB.
        'acepta_bob' => filter_var(env('STRIPE_ACEPTA_BOB', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
