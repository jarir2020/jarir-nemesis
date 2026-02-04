<?php
namespace Nemesis\Core;

class Validator {
    protected $errors = [];

    public function validate(array $data, array $rules) {
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = explode('|', $fieldRules); // Separate rules
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $data[$field] ?? null, $rule);
            }
        }
        return empty($this->errors); // Returns true if no errors
    }

    protected function applyRule($field, $value, $rule) {
        if (str_starts_with($rule, 'min:')) {
            $min = (int) str_replace('min:', '', $rule);
            if (strlen($value) < $min) {
                $this->errors[$field][] = "The $field must be at least $min characters.";
            }
        } elseif ($rule === 'required') {
            if (empty($value)) {
                $this->errors[$field][] = "The $field field is required.";
            }
        } elseif ($rule === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = "The $field must be a valid email address.";
            }
        } elseif ($rule === 'integer') {
            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                $this->errors[$field][] = "The $field must be an integer.";
            }
        }
        // Add more rules as needed
    }

    public function errors() {
        return $this->errors;
    }
}
