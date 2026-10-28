<?php

/*
|--------------------------------------------------------------------------
| Admin access control
|--------------------------------------------------------------------------
|
| Permissions are generated as "{module}.{action}" from the modules below
| and synced with `php artisan access:sync`. The super admin role bypasses
| every permission check.
|
*/

return [

    'super_admin_role' => 'super-admin',

    // Roles and permissions belong to the session guard used by the admin SPA.
    // (Sanctum switches the default guard during API requests, so it is fixed here.)
    'guard' => 'web',

    'modules' => [
        'dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],
        'users' => ['label' => 'Users', 'actions' => ['view', 'create', 'update', 'delete']],
        'roles' => ['label' => 'Roles & permissions', 'actions' => ['view', 'create', 'update', 'delete']],
        'media' => ['label' => 'Media library', 'actions' => ['view', 'create', 'update', 'delete']],
        'settings' => ['label' => 'Settings', 'actions' => ['view', 'update']],
        'menus' => ['label' => 'Menus', 'actions' => ['view', 'update']],
        'pages' => ['label' => 'Pages', 'actions' => ['view', 'create', 'update', 'delete']],
        'campaigns' => ['label' => 'Campaign pages', 'actions' => ['view', 'create', 'update', 'delete']],
        'services' => ['label' => 'Services', 'actions' => ['view', 'create', 'update', 'delete']],
        'packages' => ['label' => 'Packages', 'actions' => ['view', 'create', 'update', 'delete']],
        'bookings' => ['label' => 'Bookings', 'actions' => ['view', 'update', 'delete']],
        'payments' => ['label' => 'Payments', 'actions' => ['view', 'create']],
        'blog' => ['label' => 'Blog', 'actions' => ['view', 'create', 'update', 'delete']],
        'reviews' => ['label' => 'Reviews', 'actions' => ['view', 'create', 'update', 'delete']],
        'gallery' => ['label' => 'Gallery', 'actions' => ['view', 'create', 'update', 'delete']],
        'inquiries' => ['label' => 'Inquiries', 'actions' => ['view', 'update', 'delete']],
        'activity' => ['label' => 'Activity log', 'actions' => ['view']],
        // Not granted to any default role: only super admins open the Trash.
        'trash' => ['label' => 'Trash', 'actions' => ['view', 'restore', 'delete']],
    ],

    /*
    | Default roles created on install. "*" grants every permission of a
    | module; a list grants only those actions. Roles can be edited later in
    | the admin portal; syncing never removes permissions added there.
    */
    'roles' => [
        'super-admin' => ['label' => 'Super Admin', 'grants' => []],

        'admin' => ['label' => 'Admin', 'grants' => [
            'dashboard' => '*', 'users' => ['view'], 'media' => '*', 'settings' => '*', 'menus' => '*',
            'pages' => '*', 'campaigns' => '*', 'services' => '*', 'packages' => '*', 'bookings' => '*',
            'payments' => '*', 'blog' => '*', 'reviews' => '*', 'gallery' => '*', 'inquiries' => '*',
            'activity' => '*',
        ]],

        'content-editor' => ['label' => 'Content Editor', 'grants' => [
            'dashboard' => '*', 'media' => '*', 'menus' => '*', 'pages' => '*', 'campaigns' => '*',
            'services' => '*', 'packages' => '*', 'blog' => '*', 'reviews' => '*', 'gallery' => '*',
        ]],

        'booking-manager' => ['label' => 'Booking Manager', 'grants' => [
            'dashboard' => '*', 'bookings' => '*', 'payments' => '*', 'inquiries' => '*',
            'packages' => ['view'], 'services' => ['view'],
        ]],
    ],

];
