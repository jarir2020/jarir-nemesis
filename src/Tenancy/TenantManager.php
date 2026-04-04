<?php
declare(strict_types=1);

namespace Nemesis\Tenancy;

class TenantManager {
    protected static $currentTenantId;

    public static function setTenant($id) {
        self::$currentTenantId = $id;
    }

    public static function getTenant() {
        return self::$currentTenantId;
    }
}
