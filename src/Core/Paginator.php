<?php

namespace Nemesis\Core;

class Paginator {
    protected $items;
    protected $total;
    protected $perPage;
    protected $currentPage;
    protected $lastPage;

    public function __construct($items, $total, $perPage, $currentPage) {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
    }

    public function items() {
        return $this->items;
    }

    public function total() {
        return $this->total;
    }

    public function perPage() {
        return $this->perPage;
    }

    public function currentPage() {
        return $this->currentPage;
    }

    public function lastPage() {
        return $this->lastPage;
    }

    public function hasMorePages() {
        return $this->currentPage < $this->lastPage;
    }

    public function onFirstPage() {
        return $this->currentPage <= 1;
    }

    public function toArray() {
        return [
            'data' => array_map(function($item) {
                return is_object($item) && method_exists($item, 'toArray') 
                    ? $item->toArray() 
                    : $item;
            }, $this->items),
            'meta' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'from' => (($this->currentPage - 1) * $this->perPage) + 1,
                'to' => min($this->currentPage * $this->perPage, $this->total),
            ]
        ];
    }

    public function toJson() {
        return json_encode($this->toArray());
    }

    public function __toString() {
        return $this->toJson();
    }
}
