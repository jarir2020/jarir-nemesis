<?php
namespace App\Controllers;

use App\Models\Product;
use Nemesis\Core\Controller;
use JarirAhmed\HTTPResponse\HTTPResponse;
use Nemesis\Helpers\Helpers;

class ProductController extends Controller {
    
    public function create() {
        $data = Helpers::getInput();
        $product = new Product();
        $response = $product->create($data['name'], $data['price']);
        Helpers::json($response);
    }

    public function view($id) {
        $product = new Product();
        $response = $product->get($id);
    
        // If product is not found, return 404 response
        if (!$response) {
            HTTPResponse::notFound(); // Set 404 HTTP status
            Helpers::json([
                'error' => true,
                'message' => 'Product not found'
            ]);
            exit; // Stop further execution
        }
    
        // Return product if found
        Helpers::json($response);
    }

    public function update($id) {
        $data = Helpers::getInput();
        $product = new Product();
        $response = $product->update($id, $data['name'], $data['price']);
        Helpers::json($response);
    }

    public function delete($id) {
        $product = new Product();
        $response = $product->delete($id);
        Helpers::json($response);
    }
}
