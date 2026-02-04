<?php
namespace App\Controllers;

use App\Models\Tag;
use Nemesis\Http\Request;

class TagController extends Controller {
    public function index() {
        return $this->render('tags/index', ['tags' => (new Tag)->all()]);
    }

    public function create() {
        return $this->render('tags/create');
    }

    public function store(Request $request) {
        (new Tag)->create($request->all());
        return redirect('/tags');
    }

    public function show($id) {
        return $this->render('tags/show', ['Tag' => (new Tag)->find($id)]);
    }
}
