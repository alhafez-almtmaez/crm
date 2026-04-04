<?php

return [
    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Source Of Truth: Permissions
    |--------------------------------------------------------------------------
    |
    | Add new module permissions here (for example blog CRUD). Then run:
    | php artisan permissions:sync
    |
    */
    'permissions' => [
        'view admin dashboard',
        'activity_logs.view',
        'whatsapp.view',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated CRUD Permissions
    |--------------------------------------------------------------------------
    |
    | Each resource below is expanded to:
    | {resource}.view, {resource}.create, {resource}.update, {resource}.delete
    |
    */
    'crud_actions' => [
        'view',
        'create',
        'update',
        'delete',
    ],

    'crud_resources' => [
        'users',
        'roles',
        'plans',
        'centers',
        'groups',
        'students',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Permission Map
    |--------------------------------------------------------------------------
    |
    | Use '*' to assign all configured permissions to the role.
    |
    */
    'roles' => [
        'admin' => ['*'],
    ],
];
