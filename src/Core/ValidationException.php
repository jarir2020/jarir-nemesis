<?php
declare(strict_types=1);
namespace Nemesis\Core;

use Exception;

class ValidationException extends Exception {
    protected $errors;

    public function __construct($errors) {
        $this->errors = $errors;
        parent::__construct("Validation failed.");
    }

    public function getErrors() {
        return $this->errors;
    }
}
