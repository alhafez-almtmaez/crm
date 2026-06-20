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
        'plan_weight_rules',
        'monthly_plans',
        'centers',
        'groups',
        'students',
        'evaluations',
        'homeworks',
        'absence_rules',
        'message_templates',
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
