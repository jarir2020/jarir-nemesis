<?php
namespace Nemesis\Core;

class Validator {
    protected $errors = [];

    public function validate(array $data, array $rules) {
        $this->errors = [];
        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $data, $rule);
            }
        }
        return empty($this->errors);
    }

    protected $customMessages = [];

    public function setMessages(array $messages) {
        $this->customMessages = $messages;
    }

    protected function addError($field, $rule, $defaultMessage) {
        $message = $this->customMessages["$field.$rule"] ?? $defaultMessage;
        $this->errors[$field][] = $message;
    }

    protected function applyRule($field, $data, $rule) {
        $value = $data[$field] ?? null;

        // Parse rule name and parameters
        $params = [];
        if (str_contains($rule, ':')) {
            [$ruleName, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        } else {
            $ruleName = $rule;
        }

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, 'required', "The $field field is required.");
                }
                break;
            case 'string':
                if (!is_null($value) && !is_string($value)) {
                    $this->addError($field, 'string', "The $field must be a string.");
                }
                break;
            case 'integer':
                if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'integer', "The $field must be an integer.");
                }
                break;
            case 'numeric':
                if (!is_null($value) && !is_numeric($value)) {
                    $this->addError($field, 'numeric', "The $field must be numeric.");
                }
                break;
            case 'boolean':
                $valid = [true, false, 0, 1, '0', '1'];
                if (!is_null($value) && !in_array($value, $valid, true)) {
                    $this->addError($field, 'boolean', "The $field field must be true or false.");
                }
                break;
            case 'array':
                if (!is_null($value) && !is_array($value)) {
                    $this->addError($field, 'array', "The $field must be an array.");
                }
                break;
            case 'email':
                if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'email', "The $field must be a valid email address.");
                }
                break;
            case 'url':
                if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'url', "The $field format is invalid.");
                }
                break;
            case 'alpha':
                if (!is_null($value) && !ctype_alpha($value)) {
                    $this->addError($field, 'alpha', "The $field may only contain letters.");
                }
                break;
            case 'alpha_num':
                if (!is_null($value) && !ctype_alnum($value)) {
                    $this->addError($field, 'alpha_num', "The $field may only contain letters and numbers.");
                }
                break;
            case 'min':
                $min = (int) $params[0];
                if (is_numeric($value)) {
                    if ($value < $min) $this->addError($field, 'min', "The $field must be at least $min.");
                } elseif (is_string($value)) {
                    if (strlen($value) < $min) $this->addError($field, 'min', "The $field must be at least $min characters.");
                } elseif (is_array($value)) {
                    if (count($value) < $min) $this->addError($field, 'min', "The $field must have at least $min items.");
                }
                break;
            case 'max':
                $max = (int) $params[0];
                if (is_numeric($value)) {
                    if ($value > $max) $this->addError($field, 'max', "The $field may not be greater than $max.");
                } elseif (is_string($value)) {
                    if (strlen($value) > $max) $this->addError($field, 'max', "The $field may not be greater than $max characters.");
                } elseif (is_array($value)) {
                    if (count($value) > $max) $this->addError($field, 'max', "The $field may not have more than $max items.");
                }
                break;
            case 'between':
                $min = (int) $params[0];
                $max = (int) $params[1];
                if (is_numeric($value)) {
                    if ($value < $min || $value > $max) $this->addError($field, 'between', "The $field must be between $min and $max.");
                } elseif (is_string($value)) {
                    $len = strlen($value);
                    if ($len < $min || $len > $max) $this->addError($field, 'between', "The $field must be between $min and $max characters.");
                } elseif (is_array($value)) {
                    $count = count($value);
                    if ($count < $min || $count > $max) $this->addError($field, 'between', "The $field must have between $min and $max items.");
                }
                break;
            case 'regex':
                // Regex might contain colons, need to handle strict parameter parsing or assume simple regex
                // Simple implementation: regex:/pattern/
                if (!is_null($value) && !preg_match($params[0], $value)) {
                    $this->addError($field, 'regex', "The $field format is invalid.");
                }
                break;
            case 'confirmed':
                if ($value !== ($data[$field . '_confirmation'] ?? null)) {
                    $this->addError($field, 'confirmed', "The $field confirmation does not match.");
                }
                break;
            case 'unique':
                if (!is_null($value)) {
                    $table = $params[0];
                    $column = $params[1] ?? $field;
                    $count = \Nemesis\Core\Database::view("SELECT COUNT(*) as count FROM $table WHERE $column = ?", [$value]);
                    if (($count[0]['count'] ?? 0) > 0) {
                        $this->addError($field, 'unique', "The $field has already been taken.");
                    }
                }
                break;
            case 'exists':
                if (!is_null($value)) {
                    $table = $params[0];
                    $column = $params[1] ?? $field;
                    $count = \Nemesis\Core\Database::view("SELECT COUNT(*) as count FROM $table WHERE $column = ?", [$value]);
                    if (($count[0]['count'] ?? 0) == 0) {
                        $this->addError($field, 'exists', "The selected $field is invalid.");
                    }
                }
                break;
        }
    }
    public function errors() {
        return $this->errors;
    }
}
