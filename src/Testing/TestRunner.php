<?php
declare(strict_types=1);

namespace Nemesis\Testing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

class TestRunner {
    protected $directory;

    public function __construct($directory) {
        $this->directory = $directory;
    }

    public function run() {
        echo "Nemesis Framework Test Runner\n";
        echo "=============================\n\n";

        $testFiles = $this->findTestFiles();
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        $failures = [];

        foreach ($testFiles as $file) {
            require_once $file;
            $classes = get_declared_classes();
            $testClass = end($classes); // Heuristic: last declared class is likely the one in file
            
            // Better heuristic: Check class name matches filename
            $filename = basename($file, '.php');
            // Try namespace + filename if possible, otherwise simple check
            // For now, let's scan all declared classes that extend TestCase
            
        }
        
        // Re-scan defined classes to find TestCases
        foreach (get_declared_classes() as $className) {
            if (is_subclass_of($className, 'Nemesis\Testing\TestCase')) {
                $reflection = new ReflectionClass($className);
                if ($reflection->isAbstract()) continue;

                $methods = $reflection->getMethods();
                $instance = new $className();

                foreach ($methods as $method) {
                    if (strpos($method->getName(), 'test') === 0) {
                        $totalTests++;
                        echo "Running {$className}::{$method->getName()}... ";

                        // Reset expectedException before each test | 2026-04-03
                        $instance->_expectedException = '';
                        // Reset expectExceptionMessageContains | 2026-04-06
                        if (property_exists($instance, '_expectedExceptionMessageSubstring')) {
                            $instance->_expectedExceptionMessageSubstring = '';
                        }

                        try {
                            if (method_exists($instance, 'setUp')) $instance->setUp();
                            $method->invoke($instance);
                            if (method_exists($instance, 'tearDown')) $instance->tearDown();

                            // If expectException was declared but no exception thrown, fail
                            if ($instance->_expectedException !== '') {
                                echo "\033[31mFAIL\033[0m\n";
                                $failedTests++;
                                $failures[] = [
                                    'test'    => "{$className}::{$method->getName()}",
                                    'message' => "Expected exception {$instance->_expectedException} was not thrown.",
                                    'trace'   => '',
                                ];
                            } else {
                                echo "\033[32mPASS\033[0m\n";
                                $passedTests++;
                            }
                        } catch (\Throwable $e) {
                            // Check exception class match
                            $classMatch = $instance->_expectedException !== ''
                                && $e instanceof $instance->_expectedException;
                            // Check message substring match (if declared)
                            $msgSubstr  = property_exists($instance, '_expectedExceptionMessageSubstring')
                                ? $instance->_expectedExceptionMessageSubstring
                                : '';
                            $msgMatch   = $msgSubstr === '' || str_contains($e->getMessage(), $msgSubstr);

                            if ($classMatch && $msgMatch) {
                                echo "\033[32mPASS\033[0m\n";
                                $passedTests++;
                            } else {
                                echo "\033[31mFAIL\033[0m\n";
                                $failedTests++;
                                $failures[] = [
                                    'test'    => "{$className}::{$method->getName()}",
                                    'message' => $e->getMessage(),
                                    'trace'   => $e->getTraceAsString(),
                                ];
                            }
                        }
                    }
                }
            }
        }

        echo "\n-----------------------------\n";
        echo "Total: $totalTests, Passed: \033[32m$passedTests\033[0m, Failed: \033[31m$failedTests\033[0m\n";

        if (!empty($failures)) {
            echo "\nFailures:\n";
            foreach ($failures as $f) {
                echo "\n[x] {$f['test']}:\n    {$f['message']}\n";
            }
            exit(1);
        } else {
            exit(0);
        }
    }

    protected function findTestFiles() {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }
}
