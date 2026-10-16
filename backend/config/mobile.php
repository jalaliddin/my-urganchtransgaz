<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Oldest mobile build allowed to use the API
    |--------------------------------------------------------------------------
    |
    | The Android versionCode (the number after "+" in pubspec.yaml's
    | version). Requests from older builds get 426 "update the app". 0 turns
    | the check off. Admins normally change it on the System Settings page
    | (key `mobile.min_build`), which overrides this value.
    |
    */

    'min_build' => (int) env('MOBILE_MIN_BUILD', 0),

    /*
    |--------------------------------------------------------------------------
    | Where the update comes from
    |--------------------------------------------------------------------------
    */

    'store_url' => env('MOBILE_STORE_URL', 'https://play.google.com/store/apps/details?id=uz.urtg.urtg_mobile'),

];
