<?php

namespace JarirAhmed\PasswordGenerator;

class PasswordGenerator
{
    /**
     * Generate a random strong password.
     *
     * @param int $length Length of the password (default is random between 8 to 12).
     * @return string The generated password.
     */
    public function generate($length = null)
    {
        if ($length === null) {
            $length = rand(8, 12); // Random length between 8 to 12
        } elseif ($length < 8 || $length > 12) {
            throw new \InvalidArgumentException('Password length must be between 8 and 12.');
        }

        // Characters to be included in the password
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=<>?';
        $charactersLength = strlen($characters);
        $randomPassword = '';

        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomPassword;
    }
}
