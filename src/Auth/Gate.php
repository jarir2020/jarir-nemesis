<?php
declare(strict_types=1);

namespace Nemesis\Auth;

class Gate {
    protected static $abilities = [];
    protected static $policies = [];

    /**
     * Define a new ability.
     */
    public static function define($ability, $callback) {
        self::$abilities[$ability] = $callback;
    }

    /**
     * Register a policy for a class.
     */
    public static function policy($class, $policy) {
        self::$policies[$class] = $policy;
    }

    /**
     * Determine if the given ability should be granted for the current user.
     */
    public static function allows($ability, ...$arguments) {
        // 1. Check direct abilities
        if (isset(self::$abilities[$ability])) {
            return call_user_func_array(self::$abilities[$ability], $arguments);
        }

        // 2. Check policies if an object is passed or a string key
        $firstArgument = $arguments[0] ?? null;
        $class = is_object($firstArgument) ? get_class($firstArgument) : $firstArgument;
        
        if ($class && isset(self::$policies[$class])) {
            $policy = new self::$policies[$class]();
            if (method_exists($policy, $ability)) {
                // If it's a string key, we slice it off. If it's an object, we keep it as it's the model.
                $argsToPass = is_object($firstArgument) ? $arguments : array_slice($arguments, 1);
                return call_user_func_array([$policy, $ability], $argsToPass);
            }
        }

        // 3. RBAC Fallback: Check if the user object has the permission
        $user = $arguments[0] ?? null;
        if (is_object($user) && method_exists($user, 'hasPermission')) {
            return $user->hasPermission($ability);
        }

        return false;
    }

    /**
     * Determine if the given ability should be denied for the current user.
     */
    public static function denies($ability, $arguments = []) {
        return !self::allows($ability, $arguments);
    }

    // Added: 2026-04-03

    /**
     * Simple session-based ACL check.
     *
     * $acl = [
     *     'allowed_user_type'         => ['admin', 'manager'],
     *     'allowed_user_access_level' => [1, 2, 3],
     * ];
     * if (!Gate::checkAcl($request, $acl)) abort(403);
     *
     * Reads 'user_type' and 'user_level' from the active session.
     */
    public static function checkAcl(\Nemesis\Http\Request $request, array $acl): bool
    {
        $session    = \Nemesis\Http\Session::all();
        $userType   = $session['user_type']  ?? null;
        $userLevel  = $session['user_level'] ?? null;

        $typesOk  = in_array($userType,  $acl['allowed_user_type']         ?? [], true);
        $levelsOk = in_array($userLevel, $acl['allowed_user_access_level'] ?? [], true);

        return $typesOk && $levelsOk;
    }
}
