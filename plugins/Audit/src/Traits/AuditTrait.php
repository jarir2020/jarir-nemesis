<?php
namespace Nemesis\Plugins\Audit\Traits;

use Nemesis\Plugins\Audit\Models\Audit;

trait AuditTrait {
    public static function bootAuditTrait() {
        static::created(function ($model) {
            $model->audit('created');
        });

        static::updated(function ($model) {
            $model->audit('updated');
        });

        static::deleted(function ($model) {
            $model->audit('deleted');
        });
    }

    protected function audit($event) {
        $old = $event === 'updated' ? $this->getOriginal() : [];
        $new = $this->getAttributes();

        // If updated, only log changed attributes
        if ($event === 'updated') {
            $changes = $this->getChanges();
            $new = $changes;
            $old = array_intersect_key($old, $changes);
        }

        Audit::create([
            'user_id' => $_SESSION['user_id'] ?? null, // Assuming session auth
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => json_encode($old),
            'new_values' => json_encode($new),
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'tags' => ''
        ]);
    }
    
    // Methods getOriginal and getChanges are now available in Nemesis\Core\Model
}
