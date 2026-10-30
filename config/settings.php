<?php

/*
|--------------------------------------------------------------------------
| Settings schema
|--------------------------------------------------------------------------
|
| Every setting the admin portal can manage is declared here. The admin
| settings screens are generated from this file, so a new setting only
| needs an entry below. Stored values live in the `settings` table; anything
| not stored falls back to its default.
|
| Field options:
|   type          string|text|email|url|phone|color|media|boolean|select|
|                 timezone|json|map|secret
|   label, hint   shown in the admin
|   section       optional sub-heading the field is grouped under
|   translatable  value is stored per language: ['en' => ..., 'bn' => ...]
|   default       fallback value
|   options       allowed values for `select` (or a dynamic source name)
|   usage         media usage preset for `media` fields (config/media.php)
|   readonly      shown in the admin but cannot be changed (yet)
|   rules         extra validation rules
|
| `secret` values are encrypted at rest and never sent back to the browser.
|
*/

return [

    'cache_key' => 'settings.values',

    // How the groups are arranged in the admin settings navigation.
    'navigation' => [
        ['label' => 'Website', 'groups' => ['branding', 'theme', 'localization', 'contact', 'social', 'seo', 'tracking', 'campaigns']],
        ['label' => 'Admin portal', 'groups' => ['admin_theme', 'media', 'payment']],
    ],

    'groups' => [

        'branding' => [
            'label' => 'Branding',
            'description' => 'Name, description and the logos used across the website.',
            'icon' => 'badge',
            'fields' => [
                'site_name' => [
                    'type' => 'string', 'label' => 'Website name', 'translatable' => true, 'rules' => ['max:120'],
                    'default' => ['en' => 'Antarik Travel International', 'bn' => 'আন্তরিক ট্রাভেল ইন্টারন্যাশনাল'],
                ],
                'short_description' => [
                    'type' => 'text', 'label' => 'Short description', 'translatable' => true, 'rules' => ['max:500'],
                    'hint' => 'One or two sentences about the agency, used in the footer and as a fallback description.',
                    'default' => [
                        'en' => 'Trusted international travel services from Dhaka: Hajj & Umrah, air tickets, visa processing and tour packages.',
                        'bn' => 'ঢাকা থেকে বিশ্বস্ত আন্তর্জাতিক ট্রাভেল সেবা: হজ্জ ও উমরাহ, এয়ার টিকেট, ভিসা প্রসেসিং ও ট্যুর প্যাকেজ।',
                    ],
                ],
                'logo' => ['type' => 'media', 'usage' => 'logo', 'label' => 'Website logo', 'hint' => 'Shown in the header. A wide logo on a transparent or white background works best.', 'default' => null],
                'logo_light' => ['type' => 'media', 'usage' => 'logo', 'label' => 'Logo for dark backgrounds', 'hint' => 'A light version of the logo (white lettering, transparent background), shown over the photo at the top of pages and in the mobile menu. Without it the logo sits on a white panel there.', 'default' => null],
                'app_icon' => ['type' => 'media', 'usage' => 'icon', 'label' => 'App icon', 'hint' => 'Square image, at least 512×512. Used when the site is added to a phone home screen.', 'default' => null],
                'favicon' => ['type' => 'media', 'usage' => 'icon', 'label' => 'Favicon', 'hint' => 'Square image shown in browser tabs.', 'default' => null],
                'default_banner' => ['type' => 'media', 'usage' => 'banner', 'label' => 'Default page banner', 'hint' => 'Background for page tops that have no image of their own, such as the package list and booking pages.', 'default' => null],
                'hajj_name' => [
                    'type' => 'string', 'label' => 'Hajj & Umrah name', 'translatable' => true, 'rules' => ['max:120'], 'section' => 'Hajj & Umrah division',
                    'hint' => 'Used instead of the website name on the Hajj and Umrah pages, their packages, bookings and booking emails.',
                    'default' => ['en' => 'Antarik Hajj Kafela', 'bn' => 'আন্তরিক হজ্জ কাফেলা'],
                ],
                'hajj_logo' => ['type' => 'media', 'usage' => 'logo', 'label' => 'Hajj & Umrah logo', 'section' => 'Hajj & Umrah division', 'hint' => 'Shown in the header of those pages on white. Without one the bundled Antarik Hajj Kafela logo is used.', 'default' => null],
                'hajj_logo_light' => ['type' => 'media', 'usage' => 'logo', 'label' => 'Hajj & Umrah logo for dark backgrounds', 'section' => 'Hajj & Umrah division', 'hint' => 'A light version (light lettering, transparent background), shown over the page-top photo, in the mobile menu, the footer and emails. Without one the bundled light version is used while the bundled logo is; an uploaded Hajj logo without a light version is shown as it is.', 'default' => null],
            ],
        ],

        'theme' => [
            'label' => 'Website theme',
            'description' => 'Brand colors used by buttons, highlights and backgrounds.',
            'icon' => 'palette',
            'fields' => [
                'primary_color' => ['type' => 'color', 'label' => 'Primary color', 'hint' => 'Main brand color (headers, footer, primary buttons).', 'default' => '#012a51'],
                'secondary_color' => ['type' => 'color', 'label' => 'Secondary color', 'hint' => 'Highlights and calls to action.', 'default' => '#fdc802'],
                'accent_color' => ['type' => 'color', 'label' => 'Accent color', 'hint' => 'Small details such as badges and icons.', 'default' => '#01914d'],
            ],
        ],

        'localization' => [
            'label' => 'Language & region',
            'description' => 'Default language, timezone and currency.',
            'icon' => 'languages',
            'fields' => [
                'default_locale' => [
                    'type' => 'select', 'options' => 'locales', 'label' => 'Default language', 'default' => 'en',
                    'hint' => 'First-time visitors and search engines see the website in this language, and content in it is required. Visitors can switch with the language buttons. Add a language by adding its translation file.',
                ],
                'timezone' => ['type' => 'timezone', 'label' => 'Timezone', 'hint' => 'Used for dates, booking times and scheduled posts.', 'default' => 'Asia/Dhaka'],
                'currency' => [
                    'type' => 'select', 'label' => 'Currency', 'readonly' => true, 'default' => 'BDT',
                    'hint' => 'Fixed to Bangladeshi Taka for now; the payment gateways charge in BDT.',
                    'options' => [
                        'BDT' => 'Bangladeshi Taka (৳)',
                        'USD' => 'US Dollar ($)',
                        'AED' => 'UAE Dirham (د.إ)',
                        'SAR' => 'Saudi Riyal (﷼)',
                        'INR' => 'Indian Rupee (₹)',
                        'PKR' => 'Pakistani Rupee (₨)',
                    ],
                ],
            ],
        ],

        'contact' => [
            'label' => 'Contact details',
            'description' => 'Shown in the header, footer, contact page and structured data.',
            'icon' => 'phone',
            'fields' => [
                'primary_phone' => ['type' => 'phone', 'label' => 'Primary phone', 'section' => 'Phone', 'default' => '01827-176727'],
                'secondary_phone' => ['type' => 'phone', 'label' => 'Secondary phone', 'section' => 'Phone', 'default' => null],
                'primary_whatsapp' => ['type' => 'phone', 'label' => 'Primary WhatsApp', 'section' => 'WhatsApp', 'hint' => 'Include the country code for WhatsApp links, e.g. +880 1827-176727.', 'default' => '+880 1827-176727'],
                'secondary_whatsapp' => ['type' => 'phone', 'label' => 'Secondary WhatsApp', 'section' => 'WhatsApp', 'default' => null],
                'primary_email' => ['type' => 'email', 'label' => 'Primary email', 'section' => 'Email', 'default' => 'abdussobhan202@gmail.com'],
                'secondary_email' => ['type' => 'email', 'label' => 'Secondary email', 'section' => 'Email', 'default' => null],
                'address' => [
                    'type' => 'text', 'label' => 'Office address', 'section' => 'Office', 'translatable' => true, 'rules' => ['max:500'],
                    'default' => [
                        'en' => '65/2/1, Paramount Heights (5th & 14th Floor), Box Culvert Road, Dhaka 1000, Bangladesh',
                        'bn' => '৬৫/২/১, প্যারামাউন্ট হাইটস (৫ম ও ১৪তম তলা) বক্স কালভার্ট রোড, ঢাকা, বাংলাদেশ, ১০০০',
                    ],
                ],
                'google_map' => [
                    'type' => 'map', 'label' => 'Google Map', 'section' => 'Office', 'default' => null,
                    'hint' => 'In Google Maps choose Share → Embed a map, copy the HTML and paste it here.',
                ],
            ],
        ],

        'social' => [
            'label' => 'Social media',
            'description' => 'Profile links shown as icons. Leave a field empty to hide its icon.',
            'icon' => 'share',
            'fields' => [
                'facebook' => ['type' => 'url', 'label' => 'Facebook', 'default' => 'https://www.facebook.com/antariktravelinternational/'],
                'messenger' => ['type' => 'url', 'label' => 'Messenger', 'hint' => 'Chat link for the Facebook page, e.g. https://m.me/antariktravelinternational.', 'default' => 'https://m.me/antariktravelinternational'],
                'instagram' => ['type' => 'url', 'label' => 'Instagram', 'default' => null],
                'youtube' => ['type' => 'url', 'label' => 'YouTube', 'default' => null],
                'linkedin' => ['type' => 'url', 'label' => 'LinkedIn', 'default' => null],
                'twitter' => ['type' => 'url', 'label' => 'X (Twitter)', 'default' => null],
                'telegram' => ['type' => 'url', 'label' => 'Telegram', 'default' => null],
                'tiktok' => ['type' => 'url', 'label' => 'TikTok', 'default' => null],
            ],
        ],

        'seo' => [
            'label' => 'Default SEO',
            'description' => 'Used on any page that has no SEO details of its own.',
            'icon' => 'search',
            'fields' => [
                'meta_title' => [
                    'type' => 'string', 'label' => 'Meta title', 'rules' => ['max:70'],
                    'hint' => 'One title in the language you choose (English or Bangla). Up to 60 characters shows fully in Google results.',
                    'default' => 'Antarik Travel International | Hajj, Umrah, Air Tickets, Visa & Tours',
                ],
                'meta_description' => [
                    'type' => 'text', 'label' => 'Meta description', 'rules' => ['max:200'],
                    'hint' => 'One description in the language you choose. Around 150 characters that describe the agency in search results.',
                    'default' => 'Trusted international travel agency in Dhaka since 2016: Hajj & Umrah, air tickets, visa processing and tour packages worldwide.',
                ],
                'og_image' => ['type' => 'media', 'usage' => 'og', 'label' => 'Social share image', 'hint' => 'Shown when a link is shared on Facebook or WhatsApp. 1200×630 works best.', 'default' => null],
                'schema' => [
                    'type' => 'json', 'label' => 'Extra structured data (JSON-LD)', 'rules' => ['max:20000'], 'default' => null,
                    'hint' => 'Optional. The organisation details are generated automatically; add extra schema.org JSON here only if needed.',
                ],
            ],
        ],

        'tracking' => [
            'label' => 'Tracking & integrations',
            'description' => 'Analytics, advertising pixels and spam protection.',
            'icon' => 'activity',
            'fields' => [
                'gtm_id' => ['type' => 'string', 'label' => 'Google Tag Manager ID', 'section' => 'Google', 'hint' => 'Looks like GTM-XXXXXXX.', 'rules' => ['regex:/^GTM-[A-Z0-9]+$/'], 'default' => null],
                'ga_measurement_id' => ['type' => 'string', 'label' => 'Google Analytics measurement ID', 'section' => 'Google', 'hint' => 'Looks like G-XXXXXXXXXX. Not needed if Analytics is set up inside Tag Manager.', 'rules' => ['regex:/^G-[A-Z0-9]+$/'], 'default' => null],
                'search_console_verification' => ['type' => 'string', 'label' => 'Search Console verification code', 'section' => 'Google', 'hint' => 'Only the content value of the HTML tag verification method.', 'rules' => ['regex:/^[A-Za-z0-9_\-]+$/', 'max:120'], 'default' => null],
                'facebook_pixel_id' => ['type' => 'string', 'label' => 'Facebook Pixel ID', 'section' => 'Facebook', 'rules' => ['regex:/^[0-9]{5,20}$/'], 'default' => null],
                'recaptcha_site_key' => ['type' => 'string', 'label' => 'reCAPTCHA v3 site key', 'section' => 'Google reCAPTCHA', 'rules' => ['max:200'], 'default' => null],
                'recaptcha_secret_key' => ['type' => 'secret', 'label' => 'reCAPTCHA v3 secret key', 'section' => 'Google reCAPTCHA', 'hint' => 'Forms are protected only when both keys are set.', 'default' => null],
            ],
        ],

        'campaigns' => [
            'label' => 'Campaign pages',
            'description' => 'What visitors see when a campaign page has expired.',
            'icon' => 'megaphone',
            'fields' => [
                'expired_redirect_page' => [
                    'type' => 'select', 'options' => 'pages', 'label' => 'Redirect expired campaigns to', 'default' => null,
                    'hint' => 'Visitors opening an expired campaign link are sent here. Leave empty to use the home page.',
                ],
            ],
        ],

        'admin_theme' => [
            'label' => 'Admin theme',
            'description' => 'Colors of this admin portal.',
            'icon' => 'paintbrush',
            'fields' => [
                'primary_color' => ['type' => 'color', 'label' => 'Primary color', 'section' => 'Brand', 'default' => '#012a51'],
                'secondary_color' => ['type' => 'color', 'label' => 'Secondary color', 'section' => 'Brand', 'default' => '#fdc802'],
                'accent_color' => ['type' => 'color', 'label' => 'Accent color', 'section' => 'Brand', 'default' => '#01914d'],
                'sidebar_bg' => ['type' => 'color', 'label' => 'Background', 'section' => 'Sidebar', 'default' => '#012a51'],
                'sidebar_text' => ['type' => 'color', 'label' => 'Text', 'section' => 'Sidebar', 'default' => '#c9d6e3'],
                'sidebar_accent' => ['type' => 'color', 'label' => 'Accent', 'section' => 'Sidebar', 'default' => '#fdc802'],
                'sidebar_logo_bg' => ['type' => 'color', 'label' => 'Logo background', 'section' => 'Sidebar', 'default' => '#011f3d'],
                'header_bg' => ['type' => 'color', 'label' => 'Background', 'section' => 'Header', 'default' => '#ffffff'],
                'header_text' => ['type' => 'color', 'label' => 'Text', 'section' => 'Header', 'default' => '#0f172a'],
            ],
        ],

        'media' => [
            'label' => 'Image optimization',
            'description' => 'How uploaded images are resized and compressed.',
            'icon' => 'image',
            'fields' => [
                'optimization_mode' => [
                    'type' => 'select', 'label' => 'Optimization mode', 'default' => 'automatic',
                    'options' => [
                        'automatic' => 'Automatic: every upload is resized for its use without extra steps',
                        'manual' => 'Manual: choose sizes, quality and crop for every upload',
                    ],
                ],
            ],
        ],

        'payment' => [
            'label' => 'Payment gateways',
            'description' => 'Online payment for booking advances. Only one gateway can be active at a time.',
            'icon' => 'credit-card',
            'fields' => [
                'active_gateway' => [
                    'type' => 'select', 'label' => 'Active gateway', 'default' => null,
                    'hint' => 'Choose Off to turn off online payment (customers can still book without paying).',
                    'options' => ['sslcommerz' => 'SSLCommerz', 'dgepay' => 'DGePay'],
                ],
                'sslcommerz_mode' => ['type' => 'select', 'label' => 'Mode', 'section' => 'SSLCommerz', 'default' => 'sandbox', 'options' => ['sandbox' => 'Sandbox (testing)', 'live' => 'Live']],
                'sslcommerz_store_id' => ['type' => 'string', 'label' => 'Store ID', 'section' => 'SSLCommerz', 'rules' => ['max:120'], 'default' => null],
                'sslcommerz_store_password' => ['type' => 'secret', 'label' => 'Store password', 'section' => 'SSLCommerz', 'default' => null],
                'dgepay_mode' => ['type' => 'select', 'label' => 'Mode', 'section' => 'DGePay', 'default' => 'sandbox', 'options' => ['sandbox' => 'Sandbox (testing)', 'live' => 'Live']],
                'dgepay_client_id' => ['type' => 'string', 'label' => 'Client ID', 'section' => 'DGePay', 'rules' => ['max:200'], 'default' => null],
                'dgepay_client_secret' => ['type' => 'secret', 'label' => 'Client secret', 'section' => 'DGePay', 'default' => null],
                'dgepay_client_api_key' => ['type' => 'secret', 'label' => 'Client API key', 'section' => 'DGePay', 'default' => null],
            ],
        ],

    ],

];
