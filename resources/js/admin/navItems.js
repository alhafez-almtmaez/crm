export const adminNavItems = [
    {
        labelKey: 'nav.dashboard',
        href: '/admin/dashboard',
        icon: 'pi pi-th-large',
        permissions: ['view admin dashboard'],
    },
    {
        groupId: 'management',
        groupKey: 'nav.management',
        labelKey: 'nav.users',
        href: '/admin/users',
        icon: 'pi pi-users',
        permissions: ['users.view'],
    },
    {
        groupId: 'management',
        groupKey: 'nav.management',
        labelKey: 'nav.roles',
        href: '/admin/roles',
        icon: 'pi pi-shield',
        permissions: ['roles.view'],
    },
    {
        groupId: 'management',
        groupKey: 'nav.management',
        labelKey: 'nav.plans',
        href: '/admin/plans',
        icon: 'pi pi-bookmark',
        permissions: ['plans.view'],
    },
    {
        groupId: 'management',
        groupKey: 'nav.management',
        labelKey: 'nav.activityLogs',
        href: '/admin/activity-logs',
        icon: 'pi pi-history',
        permissions: ['activity_logs.view'],
    },
    {
        labelKey: 'nav.whatsapp',
        href: '/admin/whatsapp',
        icon: 'pi pi-whatsapp',
        roles: ['admin'],
    },
    {
        labelKey: 'nav.settings',
        href: '/admin/settings',
        icon: 'pi pi-cog',
        roles: ['admin']
    },
];

export const filterNavItemsByAccess = (items, access = {}) => {
    const userRoles = new Set(access.roles ?? []);
    const userPermissions = new Set(access.permissions ?? []);

    return (items ?? []).filter((item) => {
        const roleAllowed = !item.roles?.length || item.roles.some((role) => userRoles.has(role));
        const permissionAllowed = !item.permissions?.length || item.permissions.some((permission) => userPermissions.has(permission));

        return roleAllowed && permissionAllowed;
    });
};
