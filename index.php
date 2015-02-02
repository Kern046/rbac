<?php

require_once('vendor/autoload.php');

use PhpRbac\Database\Jf;
use PhpRbac\Rbac;

Jf::loadConfig(__DIR__.'/src/PhpRbac/Database/database_config.json');
Jf::loadConnection();
$rbac = Rbac::getInstance();
        

$rbac->reset(true);
$commonPerms = [
    'intent-to-leave',
    'referrals-public-view',
    'referrals-teams-view',
];

$role1Perms = [
    'access level-individual_aggregate'.
    'risk-indicator',
    'profile-blocks-1',
    'profile-blocks-4',
    'profile-blocks-n',
    'profile-items-2',
    'profile-items-n',
    'survey-blocks-2',
    'survey-blocks-4',
    'survey-blocks-n',
    'survey-items-2',
    'survey-items-n',
    'notes-public-view',
    'notes-teams-view',
    'contacts-public-view',
    'contacts-teams-view',
    'booking-public-view',
    'booking-teams-view',
];

$role2Perms = [
     'access-level_aggregate',
     'profile-blocks-2',
     'profile-blocks-4',
     'profile-blocks-n',
     'profile-items-2',
     'profile-items-n',
     'survey-blocks-2',
     'survey-blocks-4',
     'survey-blocks-n',
     'survey-items-2',
     'survey-items-n',
     'notes-public-create',
     'notes-teams-create',
     'contacts-public-view',
     'contacts-private-create',
     'contacts-teams-view',
     'booking-public-create',
     'booking-teams-create',
     'referrals-private-create',
];

$role3Perms = [
    'access-level_individual_aggregate',
    'risk-indicator',
    'referrals-public-create',
    'referrals-private-create',
    'referrals-teams-create',
    'receive-referrals',
];


$commonRoleId = $rbac->getManager()->getRoleManager()->add('common_role', 'Common permissions for all users');

foreach ($commonPerms as $info) {
    $permId = $rbac->getManager()->getPermissionManager()->add($info, 'Default description', $commonRoleId);
    $rbac->getManager()->getRoleManager()->assign('common_role', $permId);
}

$role1Id = $rbac->getManager()->getRoleManager()->add('role1', 'Role 1', $commonRoleId);

foreach ($role1Perms as $permName) {
    $rbac->getManager()->getPermissionManager()->add($permName, 'Default description', $role1Id);
    $rbac->getManager()->getRoleManager()->assign('role1', $permName);
}

$role2Id = $rbac->getManager()->getRoleManager()->add('role2', 'Role 2', $commonRoleId);

foreach ($role2Perms as $permName) {
    $rbac->getManager()->getPermissionManager()->add($permName, 'Default description', $role2Id);
    $rbac->getManager()->getRoleManager()->assign('role2', $permName);
}


// Create User
$users = [
    [
        'id' => 1,
        'username' => 'admin',
        'roles' => [
            'role1'
        ]
    ],
    [
        'id' => 2,
        'username' => 'editor',
        'roles' => [
            'common_role',
            'role2'
        ]
    ],
];

// Assign the proper roles.
foreach ($users as $user) {
    if (!empty($user['roles']) && is_array($user['roles'])) {
        foreach ($user['roles'] as $role) {
            $rbac->getManager()->getUserManager()->assign($role, $user['id']);
        }
    }
}


$tests = [
    $users[0]['id'] => [
        'intent to leave' => true,
        'profile-blocks-1' => true,
        'profile-blocks-2' => false,
        'survey items-3' => false,
    ],
    $users[1]['id'] => [
        'intent to leave' => true,
        'profile-blocks-1' => false,
        'profile-blocks-n' => true,
        'survey items-3' => false,
    ],
];


// Validate if a user has a particular permission.
foreach ($tests as $userId => $test) {
    foreach ($test as $permission => $hasPermission) {
        $permissionVerb = $hasPermission ? 'has' : 'doesn\'t have';
        $noPermissionVerb = $hasPermission ? 'doesn\'t have' : 'has';
        try {
            if ($rbac->check($permission, $userId) === $hasPermission) {
                echo "Success: User {$userId} $permissionVerb the '{$permission}' permission.\n";
            } else {
                echo "Failure! User {$userId} $noPermissionVerb the '{$permission}' permission.\n";
            }
        }
        catch(Exception $e) {
            $status = $hasPermission ? 'Failure' : 'Success';
            echo "$status! '{$permission}' is an unknown/invalid permission.\n";
        }
    }
}