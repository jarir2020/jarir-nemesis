<?php
declare(strict_types=1);

namespace Nemesis\Core;

class BelongsTo {
    protected $related;
    protected $child;
    protected $foreignKey;
    protected $ownerKey;

    public function __construct($related, $child, $foreignKey, $ownerKey) {
        $this->related = $related;
        $this->child = $child;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function get() {
        $query = $this->related::where($this->ownerKey, '=', $this->child->{$this->foreignKey});
        return $query->first();
    }
}
