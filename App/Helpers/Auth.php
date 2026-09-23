<?php
declare(strict_types=1);
namespace App\Helpers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

class Auth
{
    public static function user()
    {
        if (!isset($_SESSION['user'])) {
            return null;
        }
        return User::where('id', $_SESSION['user']->id);
    }

    public static function check()
    {
        return self::user() !== null;
    }

    public static function userCan(string $permission): bool
    {//
        $user = self::user();//show($user);
        if (!$user) {
            return false;
        }

        // 🚨 Super admin override
        if ($user->is_super_admin) {
            return true;
        }

        // Get role
        $role = Role::where('role_id', $user->role_id);
        if (!$role) {
            return false;
        }

        // Admin shortcut: has all permissions
        if ($role->name === 'admin') {
            return true;
        }

        // Fetch permissions for this role
        $permissions = Role::permissions($role->id);

        return in_array($permission, $permissions);
    }
}
