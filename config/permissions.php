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
        'dashboard.view',
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
        'مشرف' => [
            'dashboard.view',
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'evaluations.view',
            'evaluations.create',
            'evaluations.update',
            'evaluations.delete',
            'homeworks.view',
            'homeworks.create',
            'homeworks.update',
            'monthly_plans.view',
            'monthly_plans.create',
            'monthly_plans.update',
            'centers.view',
            'groups.view',
            'absence_rules.view',
            'message_templates.view',
            'message_templates.create',
            'message_templates.update',
            'message_templates.delete',
        ],
    ],
];
