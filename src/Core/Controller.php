<?php
namespace Nemesis\Core;

class Controller {
    protected $container;

    public function __construct() {
        $this->container = \Nemesis\Core\Container::getInstance();
    }

    protected function authorize($ability, $arguments = []) {
        if (!\Nemesis\Auth\Gate::allows($ability, $arguments)) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['error' => 'Unauthorized action.']);
            exit;
        }
    }

    protected function render($view, $data = []) {
        \Nemesis\Core\View::render($view, $data);
    }
}
