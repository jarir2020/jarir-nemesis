<?php
namespace App\Models;

use Nemesis\Core\Fluent;

class Product {

    protected $table = 'product'; // Define the table name

    public function create($name, $price) {
        // Using Fluent's insert method
        Fluent::table($this->table)->insert([
            'name' => $name,
            'price' => $price
        ]);
    
        return ['message' => 'Product created successfully.'];
    }

    public function get($id) {
        // Using Fluent's select method
        $result = Fluent::table($this->table)
                        ->whereGET('id', '=', $id)
                        ->first();  // Fetches the first record
    
        return $result ? $result : null;
    }

    public function update($id, $name, $price) {
        // Using Fluent's update method
        Fluent::table('product')
        ->whereUpdate('id', '=', $id) // Ensure this is called
        ->update([
            'name' => $name,
            'price' => $price
        ]);
        
        return ['message' => 'Product updated successfully.'];
    }

    public function delete($id) {
        // Using Fluent's delete method
        Fluent::table($this->table)
              ->whereDELETE('id', '=', $id)
              ->delete();
        
        return ['message' => 'Product deleted successfully.'];
    }
}
