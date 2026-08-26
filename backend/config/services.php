<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'alfresco' => [
        'base' => env('ALFRESCO_BASE_URL', 'http://192.168.26.38:8080/alfresco/api/-default-/public/alfresco/versions/1'),
        'user' => env('ALFRESCO_USER', 'admin'),
        'pass' => env('ALFRESCO_PASS', 'admin'),
        'site' => env('ALFRESCO_SITE', 'talentohumano'),
    ],

    // Autenticación híbrida contra Active Directory (login). Si AD_HOST está vacío,
    // el login funciona 100% como hasta ahora (clave local) — así pruebas no se ve afectado.
    'ad' => [
        'host'             => env('AD_HOST'),
        'port'             => env('AD_PORT', 389),
        'use_tls'          => env('AD_USE_TLS', false),
        'base_dn'          => env('AD_BASE_DN'),
        'bind_dn'          => env('AD_BIND_DN'),
        'bind_password'    => env('AD_BIND_PASSWORD'),
        'employee_attr'    => env('AD_EMPLOYEE_ATTR', 'employeeID'),
    ],

];
