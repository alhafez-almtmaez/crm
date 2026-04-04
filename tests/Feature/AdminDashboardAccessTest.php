<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guest users are redirected to login for admin dashboard', function () {
    $this->get('/admin/dashboard')
        ->assertRedirect('/admin/login');
});

test('authenticated users without admin role cannot access admin dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->get('/admin/dashboard')
        ->assertForbidden();
});

test('admin users can access admin dashboard and admin root redirects to dashboard', function () {
    $adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);
    $permissions = collect([
        'view admin dashboard',
        'manage users',
        'manage roles',
    ])->map(fn (string $name): Permission => Permission::create([
        'name' => $name,
        'guard_name' => 'web',
    ]));
    $adminRole->givePermissionTo($permissions);

    $user = User::factory()->create();
    $managedUser = User::factory()->create();
    $managedRole = Role::create([
        'name' => 'manager',
        'guard_name' => 'web',
    ]);
    $user->assignRole($adminRole);

    $this->actingAs($user, 'web')
        ->get('/admin')
        ->assertRedirect('/admin/dashboard');

    $this->actingAs($user, 'web')
        ->get('/admin/dashboard')
        ->assertOk();

    $this->actingAs($user, 'web')
        ->get('/admin/settings')
        ->assertOk();

    $this->actingAs($user, 'web')
        ->get('/admin/users')
        ->assertOk();

    $this->actingAs($user, 'web')
        ->get('/admin/users/create')
        ->assertOk();

    $this->actingAs($user, 'web')
        ->get("/admin/users/{$managedUser->id}/edit")
        ->assertOk();

    $this->actingAs($user, 'web')
        ->get('/admin/roles')
        ->assertOk();

    $this->actingAs($user, 'web')
        ->get('/admin/roles/create')
        ->assertOk();

    $this->actingAs($user, 'web')
        ->get("/admin/roles/{$managedRole->id}/edit")
        ->assertOk();
});
