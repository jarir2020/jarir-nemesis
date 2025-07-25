<?php
namespace App\Controllers;

use App\Models\Applications;
use Nemesis\Core\Controller;
use JarirAhmed\HTTPResponse\HTTPResponse;
use Nemesis\Helpers\Helpers;

class ApplicationsController extends Controller {

    public function create() {
        $data = Helpers::getInput();
        $app = new Applications();
        $response = $app->create(
            $data['serial'],
            $data['application_number'],
            $data['registration_number'],
            $data['name_of_worker'],
            $data['document_number'],
            $data['status']
        );
        Helpers::json($response);
    }

    public function view($serial) {
        $app = new Applications();
        $response = $app->get($serial);

        if (!$response) {
            HTTPResponse::notFound();
            Helpers::json([
                'error' => true,
                'message' => 'Application not found'
            ]);
            exit;
        }

        Helpers::json($response);
    }

    public function viewAll() {
        $app = new Applications();
        $response = $app->getAll();
        Helpers::json($response);
    }

    public function update($id) {
        $data = Helpers::getInput();
        $app = new Applications();
        $response = $app->update(
            $id, // use primary key id here
            $data['serial'],
            $data['application_number'],
            $data['registration_number'],
            $data['name_of_worker'],
            $data['document_number'],
            $data['status']
        );
        Helpers::json($response);
    }

    public function delete($serial) {
        $app = new Applications();
        $response = $app->delete($serial);
        Helpers::json($response);
    }
}
