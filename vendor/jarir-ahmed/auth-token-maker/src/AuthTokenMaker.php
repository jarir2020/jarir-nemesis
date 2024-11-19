<?php

namespace JarirAhmed\AuthTokenMaker;

use Exception;

class AuthTokenMaker
{
    /**
     * Generate a secure random auth token of a specified length.
     *
     * @param int $length Length of the token (default is 60 characters).
     * @return string The generated token.
     * @throws Exception
     */
    public function generate($length = 60)
    {
        if ($length <= 0) {
            throw new Exception("Token length must be greater than zero.");
        }

        return bin2hex(random_bytes($length / 2)); 
    }
}
