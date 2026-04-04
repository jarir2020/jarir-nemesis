<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing\Concerns;

use Nemesis\Testing\SimpleFaker;

/**
 * WithFaker
 *
 * Provides a lightweight fake-data generator with no external dependencies.
 * Mix into any TestCase:
 *
 *   class UserTest extends TestCase {
 *       use WithFaker;
 *
 *       public function testCreateUser(): void {
 *           $name  = $this->faker()->name();
 *           $email = $this->faker()->email();
 *       }
 *   }
 */
trait WithFaker
{
    protected ?SimpleFaker $fakerInstance = null;

    protected function faker(): SimpleFaker
    {
        if ($this->fakerInstance === null) {
            $this->fakerInstance = new SimpleFaker();
        }
        return $this->fakerInstance;
    }
}
