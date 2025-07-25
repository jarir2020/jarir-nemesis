<?php
namespace App\Models;

use Nemesis\Core\Fluent;

class Applications {

    protected $table = 'applications'; // Define the table name

    public function create($serial, $application_number, $registration_number, $name_of_worker, $document_number, $status) {
        Fluent::table($this->table)->insert([
            'serial' => $serial,
            'application_number' => $application_number,
            'registration_number' => $registration_number,
            'name_of_worker' => $name_of_worker,
            'document_number' => $document_number,
            'status' => $status
        ]);

        return ['message' => 'Application created successfully.'];
    }

    public function get($serial) {
        $result = Fluent::table($this->table)
                        ->whereGET('serial', '=', $serial)
                        ->first();

        return $result ? $result : null;
    }

    public function getAll() {
        //return Fluent::table($this->table)->get();
        return Fluent::table('applications')->orderBy('serial', 'ASC')->get();
    }
    

    public function update($id, $serial, $application_number, $registration_number, $name_of_worker, $document_number, $status) {
        Fluent::table($this->table)
            ->whereUpdate('id', '=', $id)  // update by primary key id
            ->update([
                'serial' => $serial,
                'application_number' => $application_number,
                'registration_number' => $registration_number,
                'name_of_worker' => $name_of_worker,
                'document_number' => $document_number,
                'status' => $status
            ]);

        return ['message' => 'Application updated successfully.'];
    }


    public function delete($serial) {
        Fluent::table($this->table)
            ->whereDELETE('serial', '=', $serial)
            ->delete();

        return ['message' => 'Application deleted successfully.'];
    }
}
