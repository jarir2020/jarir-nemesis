<?php
declare(strict_types=1);

namespace Nemesis\Activity;

use Nemesis\Core\Database;

trait RecordsActivity {
    public static function bootRecordsActivity() {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function($model) use ($event) {
                $model->recordActivity($event);
            });
        }
    }

    protected function recordActivity($event) {
        $userId = $_SESSION['user_id'] ?? null; // Simplified user resolution
        
        Database::table('activities')->insert([
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'event' => $event,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function activities() {
        // Polymorphic-like relationship (manual for now as Polymorphic not fully implemented)
        return $this->hasMany(Activity::class, 'subject_id')->where('subject_type', '=', static::class);
    }
}
