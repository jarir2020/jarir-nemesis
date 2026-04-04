<?php
declare(strict_types=1);

namespace Nemesis\Core;

class HasMany {
    protected $related;
    protected $parent;
    protected $foreignKey;
    protected $localKey;

    public function __construct($related, $parent, $foreignKey, $localKey) {
        $this->related = $related;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function get() {
        $query = $this->related::where($this->foreignKey, '=', $this->parent->{$this->localKey});
        return $query->get();
    }
}
