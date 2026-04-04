<?php
declare(strict_types=1);

namespace Nemesis\Support;

class Arr {
    public static function get($array, $key, $default = null) {
        if (is_null($key)) return $array;
        if (isset($array[$key])) return $array[$key];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return ($default instanceof \Closure) ? $default() : $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    public static function set(&$array, $key, $value) {
        if (is_null($key)) return $array = $value;
        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }
        $array[array_shift($keys)] = $value;
        return $array;
    }

    public static function has($array, $key) {
        if (!$array || is_null($key)) return false;
        if (array_key_exists($key, $array)) return true;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) return false;
            $array = $array[$segment];
        }
        return true;
    }

    public static function forget(&$array, $keys) {
        $original = &$array;
        $keys = (array) $keys;
        if (count($keys) === 0) return;
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                unset($array[$key]);
                continue;
            }
            $parts = explode('.', $key);
            $array = &$original;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }
            unset($array[array_shift($parts)]);
        }
    }

    public static function only($array, $keys) {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    public static function except($array, $keys) {
        static::forget($array, $keys);
        return $array;
    }

    public static function flatten($array, $depth = INF) {
        $result = [];
        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, static::flatten($item, $depth - 1));
            }
        }
        return $result;
    }

    public static function plumb($array, $value, $key = null) {
        $results = [];
        foreach ($array as $item) {
            $itemValue = is_object($item) ? $item->{$value} : $item[$value];
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_object($item) ? $item->{$key} : $item[$key];
                $results[$itemKey] = $itemValue;
            }
        }
        return $results;
    }

    // Added: 2026-04-03

    /**
     * Decode JSON-encoded string fields inside an array in place.
     *
     * Useful for bilingual / multi-locale models that store JSON in DB columns.
     * $fieldsOrModel may be a plain array of field names, or a model class string
     * that exposes a static getBilingualFields(): array method.
     *
     *   Arr::decodeJsonFields($row, ['name', 'description']);
     *   Arr::decodeJsonFields($row, Post::class);   // Post::getBilingualFields()
     */
    public static function decodeJsonFields(array $data, array|string $fieldsOrModel): array
    {
        $fields = is_array($fieldsOrModel)
            ? $fieldsOrModel
            : (method_exists($fieldsOrModel, 'getBilingualFields')
                ? $fieldsOrModel::getBilingualFields()
                : []);

        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $decoded = json_decode($data[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$field] = $decoded;
                }
            }
        }
        return $data;
    }

    /**
     * Return a fixed label map for custom attribute types.
     * Kept as a generic named-constant helper; callers can extend/override.
     *
     * @return array<string, string>
     */
    public static function customAttributeTypes(): array
    {
        return [
            '1' => 'Single Line Text',
            '2' => 'Multi Line Text',
            '3' => 'Date',
            '4' => 'Numeric',
            '5' => 'File',
            '6' => 'Yes/No',
            '7' => 'Single Option',
            '8' => 'Multi Option',
        ];
    }
}
