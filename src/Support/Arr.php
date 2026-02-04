<?php

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
}
