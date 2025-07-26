<?php
namespace App\Controllers;

use App\Models\Applications;
use Nemesis\Core\Controller;
use JarirAhmed\HTTPResponse\HTTPResponse;
use Nemesis\Helpers\Helpers;
use Nemesis\Core\Fluent;
use JarirAhmed\TimeHelper\TimeHelper;

class ApplicationsController extends Controller {

    public function create() {
        $data = Helpers::getInput();
        $app = new Applications();
        $lastSerial = Fluent::table('applications')->max('serial') ?? 0;
        $newSerial = $lastSerial + 1;
        $randomPart = TimeHelper::generateToken(4, 0, 8);
        $fixedPart = 'BPA/EPLKS/5605001PGB';
        $applicationNumber = $fixedPart . $randomPart;
        $response = $app->create(
            $newSerial,
            $applicationNumber,
            $data['registration_number'] ?? null,
            $data['name_of_worker'] ?? null,
            $data['document_number'] ?? null,
            $data['status'] ?? null,
            $data['employer_identification'] ?? null
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

    // Fetch existing data
    $existing = $app->find('id', $id);

    // Merge: use input if given, otherwise existing
    $response = $app->update(
        $id,
        $data['registration_number']       ?? $existing['registration_number'],
        $data['name_of_worker']            ?? $existing['name_of_worker'],
        $data['document_number']           ?? $existing['document_number'],
        $data['status']                    ?? $existing['status'],
        $data['employer_identification']   ?? $existing['employer_identification']
    );

    $updated = $app->find('id', $id);
    
    Helpers::json([
            'message' => $response['message'],
            'updated_data' => $updated
    ]);

}


    public function delete($id) {
        $app = new Applications();
        $response = $app->delete($id);
        Helpers::json($response);
    }

    public function search() {
        $data = Helpers::getInput();
        $keyword = $data['keyword'] ?? '';

        if (empty($keyword)) {
            Helpers::json([]);
            return;
        }

        $app = new Applications();
        $response = $app->search($keyword);
        Helpers::json($response);
    }


}
