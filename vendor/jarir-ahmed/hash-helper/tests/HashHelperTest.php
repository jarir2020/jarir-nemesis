<?php

namespace JarirAhmed\HashHelper\Tests;

use JarirAhmed\HashHelper\HashHelper;
use PHPUnit\Framework\TestCase;

class HashHelperTest extends TestCase
{
    protected $hashHelper;

    protected function setUp(): void
    {
        $this->hashHelper = new HashHelper();
    }

    public function testToBinary()
    {
        $this->assertEquals('1101000 1100101 1101100 1101100 1101111', $this->hashHelper->toBinary('hello'));
    }

    public function testToMd5()
    {
        $this->assertEquals('5d41402abc4b2a76b9719d911017c592', $this->hashHelper->toMd5('hello'));
    }

    public function testToSha256()
    {
        $this->assertEquals('2cf24dba5fb0a30e26e83b2ac5b0c8c5b8e39e3c6f177ac182aa711d72a78b77', $this->hashHelper->toSha256('hello'));
    }

    public function testToBase64()
    {
        $this->assertEquals('aGVsbG8=', $this->hashHelper->toBase64('hello'));
    }

    // Additional tests for other methods can be added here.
}
