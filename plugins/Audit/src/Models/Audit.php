<?php
namespace Nemesis\Plugins\Audit\Models;

use Nemesis\Core\Model;

class Audit extends Model {
    protected $table = 'audits';
    protected $fillable = [
        'user_id', 'event', 'auditable_type', 'auditable_id', 
        'old_values', 'new_values', 'url', 'ip_address', 'user_agent', 'tags'
    ];
    public $timestamps = false; // Migration has created_at, but not updated_at, so better manage manually or use CONST UPDATED_AT = null

}
