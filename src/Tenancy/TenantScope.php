<?php
declare(strict_types=1);

namespace Nemesis\Tenancy;

trait TenantScope {
    public static function bootTenantScope() {
        /*
        if (method_exists(\Nemesis\Core\Model::class, 'addGlobalScope')) {
            \Nemesis\Core\Model::addGlobalScope('tenant', function($builder) {
                $tenantId = TenantManager::getTenant();
                if ($tenantId) {
                    $builder->where('tenant_id', '=', $tenantId);
                }
            });
        }
        */
    }

    // Auto-fill tenant_id on create
    public function save($options = []) {
        if (!isset($this->attributes['tenant_id'])) {
            $this->attributes['tenant_id'] = TenantManager::getTenant();
        }
        return parent::save($options);
    }
}
