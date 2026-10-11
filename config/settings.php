<?php

/*
|--------------------------------------------------------------------------
| Settings schema
|--------------------------------------------------------------------------
|
| Every setting the admin portal can manage is declared here, grouped the
| way it is shown in the admin. Stored values live in the `settings` table;
| anything not stored falls back to the default below.
|
| Field options:
|   type          string|text|email|url|phone|color|media|boolean|select|timezone|code|secret
|   translatable  value is stored per language: ['en' => ..., 'bn' => ...]
|   default       fallback value
|   options       allowed values for `select`
|   readonly      shown in the admin but cannot be changed (yet)
|   rules         extra validation rules
|
| `secret` values are encrypted at rest and never sent back to the browser.
|
*/

return [

    'cache_key' => 'settings.values',

    'groups' => [

        'branding' => [
            'site_name' => ['type' => 'string', 'translatable' => true, 'rules' => ['max:120'], 'default' => [
                'en' => 'Antarik Hajj Kafela',
                'bn' => 'আন্তরিক হজ্জ কাফেলা',
            ]],
            'short_description' => ['type' => 'text', 'translatable' => true, 'rules' => ['max:500'], 'default' => [
                'en' => 'A Trusted Secured and Reliable Travel Agency For Making A Safe Hajj From Bangladesh',
                'bn' => 'বাংলাদেশ থেকে নিরাপদ হজ পালনের জন্য একটি বিশ্বস্ত, সুরক্ষিত ও নির্ভরযোগ্য ট্রাভেল এজেন্সি',
            ]],
            'logo' => ['type' => 'media', 'usage' => 'logo', 'default' => null],
            'app_icon' => ['type' => 'media', 'usage' => 'icon', 'default' => null],
            'favicon' => ['type' => 'media', 'usage' => 'icon', 'default' => null],
        ],

        'theme' => [
            'primary_color' => ['type' => 'color', 'default' => '#012a51'],
            'secondary_color' => ['type' => 'color', 'default' => '#fdc802'],
            'accent_color' => ['type' => 'color', 'default' => '#01914d'],
        ],

        'localization' => [
            'default_locale' => ['type' => 'select', 'options' => 'locales', 'default' => 'en'],
            'timezone' => ['type' => 'timezone', 'default' => 'Asia/Dhaka'],
            'currency' => ['type' => 'select', 'readonly' => true, 'default' => 'BDT', 'options' => [
                'BDT' => 'Bangladeshi Taka (৳)',
                'USD' => 'US Dollar ($)',
                'AED' => 'UAE Dirham (د.إ)',
                'SAR' => 'Saudi Riyal (﷼)',
                'INR' => 'Indian Rupee (₹)',
                'PKR' => 'Pakistani Rupee (₨)',
            ]],
        ],

        'contact' => [
            'primary_phone' => ['type' => 'phone', 'default' => '01711-381901'],
            'secondary_phone' => ['type' => 'phone', 'default' => '01851074630'],
            'primary_email' => ['type' => 'email', 'default' => 'antariktravels@yahoo.com'],
            'secondary_email' => ['type' => 'email', 'default' => 'info@antarikhajjkafela.com'],
            'primary_whatsapp' => ['type' => 'phone', 'default' => '01814371475'],
            'secondary_whatsapp' => ['type' => 'phone', 'default' => null],
            'address' => ['type' => 'text', 'translatable' => true, 'rules' => ['max:500'], 'default' => [
                'en' => '65/2/1, Paramount Heights (5th & 14th Floor), Box Culvert Road, Dhaka 1000, Bangladesh',
                'bn' => '৬৫/২/১, প্যারামাউন্ট হাইটস (৫ম ও ১৪তম তলা) বক্স কালভার্ট রোড, ঢাকা, বাংলাদেশ, ১০০০',
            ]],
            'google_map' => ['type' => 'code', 'rules' => ['max:5000'], 'default' => null],
        ],

        'social' => [
            'facebook' => ['type' => 'url', 'default' => 'https://facebook.com/antarikhajjkafela'],
            'instagram' => ['type' => 'url', 'default' => null],
            'linkedin' => ['type' => 'url', 'default' => null],
            'twitter' => ['type' => 'url', 'default' => null],
            'telegram' => ['type' => 'url', 'default' => null],
            'youtube' => ['type' => 'url', 'default' => null],
            'tiktok' => ['type' => 'url', 'default' => null],
        ],

        'seo' => [
            'meta_title' => ['type' => 'string', 'translatable' => true, 'rules' => ['max:70'], 'default' => [
                'en' => 'Antarik Hajj Kafela | Trusted Hajj & Umrah Agency in Bangladesh',
                'bn' => 'আন্তরিক হজ্জ কাফেলা | বাংলাদেশের বিশ্বস্ত হজ ও উমরাহ এজেন্সি',
            ]],
            'meta_description' => ['type' => 'text', 'translatable' => true, 'rules' => ['max:200'], 'default' => [
                'en' => 'Safe, comfortable and scholar-guided Hajj & Umrah packages from Bangladesh since 2016.',
                'bn' => '২০১৬ সাল থেকে বাংলাদেশ থেকে নিরাপদ, আরামদায়ক ও আলেমদের তত্ত্বাবধানে হজ ও উমরাহ প্যাকেজ।',
            ]],
            'og_image' => ['type' => 'media', 'usage' => 'og', 'default' => null],
            'schema' => ['type' => 'code', 'rules' => ['max:20000'], 'default' => null],
        ],

        'tracking' => [
            'gtm_id' => ['type' => 'string', 'rules' => ['nullable', 'regex:/^GTM-[A-Z0-9]+$/'], 'default' => null],
            'ga_measurement_id' => ['type' => 'string', 'rules' => ['nullable', 'regex:/^G-[A-Z0-9]+$/'], 'default' => null],
            'search_console_verification' => ['type' => 'string', 'rules' => ['max:200'], 'default' => null],
            'facebook_pixel_id' => ['type' => 'string', 'rules' => ['nullable', 'regex:/^[0-9]{5,20}$/'], 'default' => null],
            'recaptcha_site_key' => ['type' => 'string', 'rules' => ['max:200'], 'default' => null],
            'recaptcha_secret_key' => ['type' => 'secret', 'default' => null],
        ],

        'campaigns' => [
            'expired_redirect_page' => ['type' => 'select', 'options' => 'pages', 'default' => null],
        ],

        'admin_theme' => [
            'primary_color' => ['type' => 'color', 'default' => '#012a51'],
            'secondary_color' => ['type' => 'color', 'default' => '#fdc802'],
            'accent_color' => ['type' => 'color', 'default' => '#01914d'],
            'sidebar_bg' => ['type' => 'color', 'default' => '#012a51'],
            'sidebar_text' => ['type' => 'color', 'default' => '#c9d6e3'],
            'sidebar_accent' => ['type' => 'color', 'default' => '#fdc802'],
            'sidebar_logo_bg' => ['type' => 'color', 'default' => '#011f3d'],
            'header_bg' => ['type' => 'color', 'default' => '#ffffff'],
            'header_text' => ['type' => 'color', 'default' => '#0f172a'],
        ],

        'media' => [
            'optimization_mode' => ['type' => 'select', 'default' => 'automatic', 'options' => [
                'automatic' => 'Automatic',
                'manual' => 'Manual',
            ]],
        ],

        'payment' => [
            'active_gateway' => ['type' => 'select', 'default' => null, 'options' => [
                'sslcommerz' => 'SSLCommerz',
                'dgepay' => 'DGePay',
            ]],
            'sslcommerz_mode' => ['type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live']],
            'sslcommerz_store_id' => ['type' => 'string', 'default' => null],
            'sslcommerz_store_password' => ['type' => 'secret', 'default' => null],
            'dgepay_mode' => ['type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live']],
            'dgepay_client_id' => ['type' => 'string', 'default' => null],
            'dgepay_client_secret' => ['type' => 'secret', 'default' => null],
            'dgepay_client_api_key' => ['type' => 'secret', 'default' => null],
        ],

    ],

];
