<?php
namespace App\Modules\Blog\Controllers;

use Nemesis\Core\Controller;

class BlogController extends Controller {
    public function index() {
        return $this->render('blog::index', ['name' => 'Blog']);
    }
}
