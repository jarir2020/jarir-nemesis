<?php
declare(strict_types=1);
namespace Nemesis\Core;

class Validator {
    protected $errors = [];

    public function validate(array $data, array $rules) {
        $this->errors = [];
        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $valueExists = array_key_exists($field, $data);
            $value = $valueExists ? $data[$field] : null;
            $ruleNames = array_map(function ($rule) {
                return str_contains($rule, ':') ? explode(':', $rule, 2)[0] : $rule;
            }, $rulesArray);

            if (in_array('sometimes', $ruleNames, true) && !$valueExists) {
                continue;
            }

            if (in_array('present', $ruleNames, true) && !$valueExists) {
                $this->addError($field, 'present', "The $field field must be present.");
                continue;
            }

            if (in_array('nullable', $ruleNames, true) && (!$valueExists || $this->isEmptyValue($value)) && !in_array('required', $ruleNames, true)) {
                continue;
            }

            foreach ($rulesArray as $rule) {
                if (!$this->applyRule($field, $data, $rule, $valueExists, $value)) {
                    break;
                }
            }
        }
        return empty($this->errors);
    }

    protected $customMessages = [];

    public function setMessages(array $messages) {
        $this->customMessages = $messages;
        return $this;
    }

    protected function addError($field, $rule, $defaultMessage) {
        $message = $this->customMessages["$field.$rule"] ?? $defaultMessage;
        $this->errors[$field][] = $message;
    }

    public function passes(array $data, array $rules): bool {
        return $this->validate($data, $rules);
    }

    public function fails(array $data, array $rules): bool {
        return !$this->validate($data, $rules);
    }

    protected function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    protected function normalizeDate(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (!is_scalar($value) && !($value instanceof \Stringable)) {
            return null;
        }
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : $timestamp;
    }

    protected function isFilledValue(array $data, string $field): bool
    {
        return array_key_exists($field, $data) && !$this->isEmptyValue($data[$field]);
    }

    protected function anyFieldFilled(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($this->isFilledValue($data, (string) $field)) {
                return true;
            }
        }

        return false;
    }

    protected function allFieldsFilled(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!$this->isFilledValue($data, (string) $field)) {
                return false;
            }
        }

        return $fields !== [];
    }

    protected function anyFieldMissing(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!$this->isFilledValue($data, (string) $field)) {
                return true;
            }
        }

        return false;
    }

    protected function allFieldsMissing(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($this->isFilledValue($data, (string) $field)) {
                return false;
            }
        }

        return $fields !== [];
    }

    protected function fieldMatchesAny(array $data, string $field, array $values): bool
    {
        if (!array_key_exists($field, $data)) {
            return false;
        }

        return in_array($data[$field], $values, true);
    }

    protected function applyRule($field, $data, $rule, bool $valueExists = true, mixed $value = null) {

        // Parse rule name and parameters
        $params = [];
        if (str_contains($rule, ':')) {
            [$ruleName, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        } else {
            $ruleName = $rule;
        }

        switch ($ruleName) {
            case 'sometimes':
            case 'present':
                return true;
            case 'nullable':
                if (!$valueExists || $this->isEmptyValue($value)) {
                    return false;
                }
                break;
            case 'required':
                if (!$valueExists || $this->isEmptyValue($value)) {
                    $this->addError($field, 'required', "The $field field is required.");
                }
                break;
            case 'filled':
                if ($valueExists && $this->isEmptyValue($value)) {
                    $this->addError($field, 'filled', "The $field field must have a value.");
                }
                break;
            case 'string':
                if (!is_null($value) && !is_string($value)) {
                    $this->addError($field, 'string', "The $field must be a string.");
                }
                break;
            case 'integer':
                if (!is_null($value) && filter_var($value, FILTER_VALIDATE_INT) === false) {
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
                if (!is_null($value) && (!is_string($value) || !ctype_alpha($value))) {
                    $this->addError($field, 'alpha', "The $field may only contain letters.");
                }
                break;
            case 'alpha_num':
                if (!is_null($value) && (!is_string($value) || !ctype_alnum($value))) {
                    $this->addError($field, 'alpha_num', "The $field may only contain letters and numbers.");
                }
                break;
            case 'accepted':
                $accepted = [true, 1, '1', 'yes', 'on', 'true'];
                if (!in_array($value, $accepted, true)) {
                    $this->addError($field, 'accepted', "The $field must be accepted.");
                }
                break;
            case 'accepted_if':
                $otherField = $params[0] ?? '';
                $otherValues = array_slice($params, 1);
                $accepted = [true, 1, '1', 'yes', 'on', 'true'];
                if ($this->fieldMatchesAny($data, (string) $otherField, $otherValues) && !in_array($value, $accepted, true)) {
                    $this->addError($field, 'accepted_if', "The $field must be accepted when $otherField matches.");
                }
                break;
            case 'in':
                if (!in_array($value, $params, true)) {
                    $this->addError($field, 'in', "The selected $field is invalid.");
                }
                break;
            case 'not_in':
                if (in_array($value, $params, true)) {
                    $this->addError($field, 'not_in', "The selected $field is invalid.");
                }
                break;
            case 'same':
                $otherField = $params[0] ?? '';
                if ($value !== ($data[$otherField] ?? null)) {
                    $this->addError($field, 'same', "The $field and $otherField must match.");
                }
                break;
            case 'different':
                $otherField = $params[0] ?? '';
                if ($value === ($data[$otherField] ?? null)) {
                    $this->addError($field, 'different', "The $field and $otherField must be different.");
                }
                break;
            case 'required_if':
                $otherField = (string) ($params[0] ?? '');
                $otherValues = array_slice($params, 1);
                if ($this->fieldMatchesAny($data, $otherField, $otherValues) && (!$valueExists || $this->isEmptyValue($value))) {
                    $this->addError($field, 'required_if', "The $field field is required when $otherField is present.");
                }
                break;
            case 'required_unless':
                $otherField = (string) ($params[0] ?? '');
                $otherValues = array_slice($params, 1);
                if (!$this->fieldMatchesAny($data, $otherField, $otherValues) && (!$valueExists || $this->isEmptyValue($value))) {
                    $this->addError($field, 'required_unless', "The $field field is required unless $otherField is one of the expected values.");
                }
                break;
            case 'required_with':
                $otherFields = $params;
                if ($this->anyFieldFilled($data, $otherFields) && (!$valueExists || $this->isEmptyValue($value))) {
                    $this->addError($field, 'required_with', "The $field field is required when any related field is present.");
                }
                break;
            case 'required_with_all':
                $otherFields = $params;
                if ($this->allFieldsFilled($data, $otherFields) && (!$valueExists || $this->isEmptyValue($value))) {
                    $this->addError($field, 'required_with_all', "The $field field is required when all related fields are present.");
                }
                break;
            case 'required_without':
                $otherFields = $params;
                if ($this->anyFieldMissing($data, $otherFields) && (!$valueExists || $this->isEmptyValue($value))) {
                    $this->addError($field, 'required_without', "The $field field is required when any related field is missing.");
                }
                break;
            case 'required_without_all':
                $otherFields = $params;
                if ($this->allFieldsMissing($data, $otherFields) && (!$valueExists || $this->isEmptyValue($value))) {
                    $this->addError($field, 'required_without_all', "The $field field is required when all related fields are missing.");
                }
                break;
            case 'date':
                if ($this->normalizeDate($value) === null) {
                    $this->addError($field, 'date', "The $field is not a valid date.");
                }
                break;
            case 'before':
                $comparison = $this->normalizeDate($params[0] ?? null);
                $current    = $this->normalizeDate($value);
                if ($comparison === null || $current === null || $current >= $comparison) {
                    $this->addError($field, 'before', "The $field must be a date before {$params[0]}.");
                }
                break;
            case 'after':
                $comparison = $this->normalizeDate($params[0] ?? null);
                $current    = $this->normalizeDate($value);
                if ($comparison === null || $current === null || $current <= $comparison) {
                    $this->addError($field, 'after', "The $field must be a date after {$params[0]}.");
                }
                break;
            case 'digits':
                $expected = (int) ($params[0] ?? 0);
                $stringValue = (string) $value;
                if (!preg_match('/^\d+$/', $stringValue) || strlen($stringValue) !== $expected) {
                    $this->addError($field, 'digits', "The $field must be $expected digits.");
                }
                break;
            case 'digits_between':
                $min = (int) ($params[0] ?? 0);
                $max = (int) ($params[1] ?? 0);
                $stringValue = (string) $value;
                $length = strlen($stringValue);
                if (!preg_match('/^\d+$/', $stringValue) || $length < $min || $length > $max) {
                    $this->addError($field, 'digits_between', "The $field must be between $min and $max digits.");
                }
                break;
            case 'uuid':
                if (!is_string($value) || !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value)) {
                    $this->addError($field, 'uuid', "The $field must be a valid UUID.");
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
            case 'array_keys':
            case 'required_array_keys':
                $requiredKeys = $params;
                if (!is_array($value)) {
                    $this->addError($field, $ruleName, "The $field must be an array.");
                    break;
                }
                foreach ($requiredKeys as $requiredKey) {
                    if (!array_key_exists($requiredKey, $value)) {
                        $this->addError($field, $ruleName, "The $field must contain keys: " . implode(', ', $requiredKeys) . '.');
                        break;
                    }
                }
                break;
            case 'present_if':
            case 'present_unless':
                $otherField = (string) ($params[0] ?? '');
                $otherValues = array_slice($params, 1);
                $shouldPresent = $ruleName === 'present_if'
                    ? $this->fieldMatchesAny($data, $otherField, $otherValues)
                    : !$this->fieldMatchesAny($data, $otherField, $otherValues);
                if ($shouldPresent && !$valueExists) {
                    $this->addError($field, 'present', "The $field field must be present.");
                }
                break;
            case 'prohibited':
                if ($valueExists && !$this->isEmptyValue($value)) {
                    $this->addError($field, 'prohibited', "The $field field is prohibited.");
                }
                break;
            case 'prohibited_if':
            case 'prohibited_unless':
                $otherField = (string) ($params[0] ?? '');
                $otherValues = array_slice($params, 1);
                $isTriggered = $ruleName === 'prohibited_if'
                    ? $this->fieldMatchesAny($data, $otherField, $otherValues)
                    : !$this->fieldMatchesAny($data, $otherField, $otherValues);
                if ($isTriggered && $valueExists && !$this->isEmptyValue($value)) {
                    $this->addError($field, 'prohibited', "The $field field is prohibited.");
                }
                break;
            case 'prohibits':
                if ($valueExists && !$this->isEmptyValue($value)) {
                    foreach ($params as $otherField) {
                        if ($this->isFilledValue($data, (string) $otherField)) {
                            $this->addError($field, 'prohibits', "The $field field prohibits {$otherField}.");
                            break;
                        }
                    }
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
            default:
                break;
        }

        return true;
    }
    public function errors() {
        return $this->errors;
    }
}
