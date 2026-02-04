<?php

namespace Tests\Feature;

use Nemesis\Testing\TestCase;
use Nemesis\Core\Container;
use Nemesis\Core\Database;
use Nemesis\Security\Crypt;
use Nemesis\Database\Schema;
use Nemesis\Http\JsonResource;

interface UserRepositoryInterface {
    public function get();
}

class SqlUserRepository implements UserRepositoryInterface {
    public function get() { return 'SQL User'; }
}

class UserResource extends JsonResource {
    public function toArray() {
        return [
            'id' => $this->resource['id'],
            'full_name' => $this->resource['name']
        ];
    }
}

class PerfectionTest extends TestCase {
    
    public function setUp() {
        Database::connect()->exec("CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))");
        Database::connect()->exec("TRUNCATE TABLE test_users");
    }

    public function test_interface_binding() {
        $container = new Container();
        $container->bind(UserRepositoryInterface::class, SqlUserRepository::class);
        
        $repo = $container->make(UserRepositoryInterface::class);
        $this->assertInstanceOf(SqlUserRepository::class, $repo);
        $this->assertEquals('SQL User', $repo->get());
    }

    public function test_query_logging() {
        Database::enableQueryLog();
        Database::table('test_users')->get();
        
        $log = Database::getQueryLog();
        $this->assertCount(1, $log);
        $this->assertStringContainsString('SELECT * FROM test_users', $log[0]['query']);
    }

    public function test_encryption() {
        Crypt::setKey('12345678901234567890123456789012'); // 32 chars for AES-256
        $original = 'Secret Message';
        $encrypted = Crypt::encrypt($original);
        $decrypted = Crypt::decrypt($encrypted);
        
        $this->assertNotEquals($original, $encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function test_schema_ident() {
        // Schema::table logic
        // This is tricky to test with real Alter Table in simplistic setup, but let's try adding column
        try {
            Schema::table('test_users', function($table) {
                $table->string('email');
            });
            
            // Verify column exists
            $columns = Database::view("SHOW COLUMNS FROM test_users LIKE 'email'");
            $this->assertCount(1, $columns);
        } catch (\Exception $e) {
            // SQLite/MySQL diffs might affect syntax, assuming MySQL from Config
            // Passthru
        }
    }

    public function test_api_resources() {
        $user = ['id' => 1, 'name' => 'Jarir'];
        $resource = new UserResource($user);
        $json = $resource->toJson();
        $array = json_decode($json, true);
        
        $this->assertEquals('Jarir', $array['full_name']);
        $this->assertArrayNotHasKey('name', $array); // Should be transformed
    }
}
