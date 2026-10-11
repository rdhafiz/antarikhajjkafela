<?php

/*
|--------------------------------------------------------------------------
| Menus
|--------------------------------------------------------------------------
|
| Locations are the places on the website that show a menu. Each menu item
| links to one of the link types below; more types (pages, services,
| packages, blog categories) are added as those modules are built.
|
*/

return [

    'locations' => [
        'primary' => [
            'name' => 'Main menu',
            'description' => 'The navigation bar at the top of every page. Items can have dropdowns up to three levels deep.',
            'max_depth' => 3,
        ],
        'footer' => [
            'name' => 'Footer links',
            'description' => 'Quick links in the website footer.',
            'max_depth' => 1,
        ],
    ],

    /*
    | Built-in website pages that menu items can link to.
    */
    'routes' => [
        'home' => ['label' => 'Home', 'path' => '/'],
        'hajj' => ['label' => 'Hajj packages', 'path' => '/hajj'],
        'umrah' => ['label' => 'Umrah packages', 'path' => '/umrah'],
        'packages' => ['label' => 'All packages', 'path' => '/packages'],
        'services' => ['label' => 'Services', 'path' => '/services'],
        'gallery' => ['label' => 'Gallery', 'path' => '/gallery'],
        'reviews' => ['label' => 'Reviews', 'path' => '/reviews'],
        'blog' => ['label' => 'Blog & news', 'path' => '/blog'],
        'about' => ['label' => 'About us', 'path' => '/about'],
        'contact' => ['label' => 'Contact', 'path' => '/contact'],
        'booking_status' => ['label' => 'Check booking status', 'path' => '/booking/status'],
    ],

];
