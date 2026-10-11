<?php

/*
|--------------------------------------------------------------------------
| Media library
|--------------------------------------------------------------------------
|
| Every image is uploaded for a "usage" (hero, card, gallery, ...). Each
| usage defines the WebP variants generated from the original. In manual
| optimization mode the admin can override width, height, fit, quality and
| crop for each variant before the upload completes.
|
| Variant options:
|   width, height  target box in pixels (null = keep aspect ratio)
|   fit            "scale" keeps the whole image inside the box,
|                  "cover" fills the box and crops the overflow
|   quality        WebP quality, 1-100
|
| Images are never upscaled.
|
*/

return [

    'disk' => env('MEDIA_DISK', 'public'),

    // Untouched originals are kept privately for reprocessing.
    'originals_disk' => env('MEDIA_ORIGINALS_DISK', 'local'),

    'directory' => 'media',

    'max_upload_kb' => 10240,

    'max_dimension' => 6000,

    'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'],

    'default_quality' => 80,

    // Small preview generated for every image, used by the admin media grid.
    'admin_variant' => ['width' => 400, 'height' => 400, 'fit' => 'scale', 'quality' => 70],

    'usages' => [

        'general' => [
            'label' => 'General',
            'variants' => [
                'lg' => ['width' => 1600, 'height' => null, 'fit' => 'scale'],
                'md' => ['width' => 960, 'height' => null, 'fit' => 'scale'],
                'sm' => ['width' => 480, 'height' => null, 'fit' => 'scale'],
            ],
        ],

        'hero' => [
            'label' => 'Hero / full-width background',
            'variants' => [
                'xl' => ['width' => 1920, 'height' => null, 'fit' => 'scale'],
                'lg' => ['width' => 1440, 'height' => null, 'fit' => 'scale'],
                'md' => ['width' => 1024, 'height' => null, 'fit' => 'scale'],
                'sm' => ['width' => 640, 'height' => null, 'fit' => 'scale'],
            ],
        ],

        'banner' => [
            'label' => 'Page banner',
            'variants' => [
                'lg' => ['width' => 1600, 'height' => 600, 'fit' => 'cover'],
                'md' => ['width' => 1024, 'height' => 420, 'fit' => 'cover'],
                'sm' => ['width' => 640, 'height' => 360, 'fit' => 'cover'],
            ],
        ],

        'card' => [
            'label' => 'Card image',
            'variants' => [
                'md' => ['width' => 800, 'height' => 600, 'fit' => 'cover'],
                'sm' => ['width' => 400, 'height' => 300, 'fit' => 'cover'],
            ],
        ],

        'content' => [
            'label' => 'Content image',
            'variants' => [
                'lg' => ['width' => 1280, 'height' => null, 'fit' => 'scale'],
                'md' => ['width' => 800, 'height' => null, 'fit' => 'scale'],
                'sm' => ['width' => 480, 'height' => null, 'fit' => 'scale'],
            ],
        ],

        'gallery' => [
            'label' => 'Gallery photo',
            'variants' => [
                'lg' => ['width' => 1600, 'height' => 1600, 'fit' => 'scale'],
                'thumb' => ['width' => 480, 'height' => 480, 'fit' => 'cover'],
            ],
        ],

        'avatar' => [
            'label' => 'Avatar / person photo',
            'variants' => [
                'md' => ['width' => 320, 'height' => 320, 'fit' => 'cover'],
                'sm' => ['width' => 96, 'height' => 96, 'fit' => 'cover'],
            ],
        ],

        'logo' => [
            'label' => 'Logo',
            'variants' => [
                'md' => ['width' => 640, 'height' => null, 'fit' => 'scale', 'quality' => 90],
                'sm' => ['width' => 320, 'height' => null, 'fit' => 'scale', 'quality' => 90],
            ],
        ],

        'icon' => [
            'label' => 'App icon / favicon',
            'variants' => [
                '512' => ['width' => 512, 'height' => 512, 'fit' => 'scale', 'quality' => 90],
                '192' => ['width' => 192, 'height' => 192, 'fit' => 'scale', 'quality' => 90],
                '32' => ['width' => 32, 'height' => 32, 'fit' => 'scale', 'quality' => 90],
            ],
        ],

        'og' => [
            'label' => 'Social share (Open Graph)',
            'variants' => [
                'og' => ['width' => 1200, 'height' => 630, 'fit' => 'cover', 'quality' => 85],
            ],
        ],

    ],

];
