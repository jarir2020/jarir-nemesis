<?php
declare(strict_types=1);

namespace Nemesis\Auth\Traits;

use Nemesis\Core\Database;
use PDO;

trait HasRoles {
    protected $roles = null;
    protected $permissions = null;

    public function roles() {
        if ($this->roles !== null) return $this->roles;

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT r.* FROM roles r 
            INNER JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $this->id]);
        $this->roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->roles;
    }

    public function permissions() {
        if ($this->permissions !== null) return $this->permissions;

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT p.* FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $this->id]);
        $this->permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->permissions;
    }

    public function hasRole($roleSlug) {
        foreach ($this->roles() as $role) {
            if ($role['slug'] === $roleSlug) return true;
        }
        return false;
    }

    public function hasPermission($permissionSlug) {
        foreach ($this->permissions() as $permission) {
            if ($permission['slug'] === $permissionSlug) return true;
        }
        return false;
    }
}
