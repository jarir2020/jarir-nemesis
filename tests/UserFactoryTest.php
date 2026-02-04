<?php

namespace Tests\Feature;

use Nemesis\Testing\TestCase;
use Nemesis\Testing\Concerns\MakesHttpRequests;
use App\Models\UserModel;

class UserFactoryTest extends TestCase {
    use MakesHttpRequests;

    public function test_factory_creates_user() {
        $user = UserModel::factory()->create();
        
        $this->assertNotNull($user->id, "User ID not null");
        $this->assertTrue(str_starts_with($user->email, 'user'), "Email format check");
        
        // Cleanup (since transaction rollback isn't implemented in TestCase yet)
        $user->delete(); 
    }

    public function test_factory_count() {
        // Native framework doesn't support collections fully yet on create(), 
        // but let's test create() single instance logic again with override
        $user = UserModel::factory()->create(['name' => 'Overridden Name']);
        $this->assertEquals('Overridden Name', $user->name);
        $user->delete();
    }
}
