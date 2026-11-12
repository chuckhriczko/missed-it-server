<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Logalyzer API URL
    |--------------------------------------------------------------------------
    | The base URL of your Logalyzer instance.
    | e.g. https://logalyzer.example.com
    */
    'url' => env('LOGALYZER_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    | Your Logalyzer API token.  Create one in the Logalyzer UI under
    | Settings → API Tokens.
    */
    'token' => env('LOGALYZER_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Log Name
    |--------------------------------------------------------------------------
    | The name of the log group that entries will be grouped under in the
    | Logalyzer UI.  Defaults to the app name from config/app.php.
    */
    'log_name' => env('LOGALYZER_LOG_NAME', env('APP_NAME', 'Chain Reaction')),

    /*
    |--------------------------------------------------------------------------
    | Environment Tag
    |--------------------------------------------------------------------------
    | Tags every entry with the current environment so you can filter by
    | production vs staging in the Logalyzer UI.
    */
    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Log Type
    |--------------------------------------------------------------------------
    | Hints the Logalyzer parser about the format of these logs.
    */
    'log_type' => 'laravel',

    /*
    |--------------------------------------------------------------------------
    | Project ID
    |--------------------------------------------------------------------------
    | Pin logs to a specific Logalyzer project.  When set, this value is sent
    | as `project_id` in every ingest request.  If your API token is already
    | scoped to a project at creation time, this env var is optional — the
    | backend will use the token's project.  Set it here to override or when
    | your token is project-agnostic.
    */
    'project_id' => env('LOGALYZER_PROJECT_ID') ? (int) env('LOGALYZER_PROJECT_ID') : null,

    /*
    |--------------------------------------------------------------------------
    | Minimum Level
    |--------------------------------------------------------------------------
    | Monolog level name.  Entries below this level are silently ignored.
    | Options: debug, info, notice, warning, error, critical, alert, emergency
    */
    'level' => env('LOGALYZER_LEVEL', 'debug'),
];
