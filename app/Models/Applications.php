<?php
namespace App\Models;

use Nemesis\Core\Fluent;

class Applications {

    protected $table = 'applications'; // Define the table name
    
    public function find($column, $value) {
    return Fluent::table($this->table)
                 ->whereGET($column, '=', $value)
                 ->first();
}

    public function create($serial, $application_number, $registration_number, $name_of_worker, $document_number, $status, $employer_identification) {
        Fluent::table($this->table)->insert([
            'serial' => $serial,
            'application_number' => $application_number,
            'registration_number' => $registration_number,
            'name_of_worker' => $name_of_worker,
            'document_number' => $document_number,
            'status' => $status,
            'employer_identification' => $employer_identification
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
    

    public function update($id, $registration_number = null, $name_of_worker = null, $document_number = null, $status = null, $employer_identification = null) {
        $existing = Fluent::table($this->table)
                          ->whereGET('id', '=', $id)
                          ->first();
    
        if (!$existing) {
            return ['error' => 'Application not found.'];
        }
    
        Fluent::table($this->table)
            ->whereUpdate('id', '=', $id)
            ->update([
                'registration_number' => $registration_number ?? $existing['registration_number'],
                'name_of_worker' => $name_of_worker ?? $existing['name_of_worker'],
                'document_number' => $document_number ?? $existing['document_number'],
                'status' => $status ?? $existing['status'],
                'employer_identification' => $employer_identification ?? $existing['employer_identification']
            ]);
    
        return ['message' => 'Application updated successfully.'];
    }




    public function delete($id) {
        Fluent::table($this->table)
            ->whereDELETE('id', '=', $id)
            ->delete();

        return ['message' => 'Application deleted successfully.'];
    }


    public function search($keyword) {
        $query = Fluent::table($this->table);

        $columns = ['serial', 'application_number', 'registration_number', 'name_of_worker', 'document_number', 'status'];

        // Build WHERE with OR for exact match
        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $query = $query->whereGET($column, '=', $keyword);
            } else {
                $query = $query->orWhere($column, '=', $keyword);
            }
        }

        return $query->get();
    }


}
