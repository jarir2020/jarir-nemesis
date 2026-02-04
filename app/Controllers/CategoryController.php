<?php
namespace App\Controllers;

use App\Models\Category;
use Nemesis\Http\Request;

class CategoryController extends Controller {
    public function index() {
        return $this->render('categorys/index', ['categorys' => (new Category)->all()]);
    }

    public function create() {
        return $this->render('categorys/create');
    }

    public function store(Request $request) {
        (new Category)->create($request->all());
        return redirect('/categorys');
    }

    public function show($id) {
        return $this->render('categorys/show', ['Category' => (new Category)->find($id)]);
    }
}
