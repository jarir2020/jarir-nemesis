<?php
namespace App\Models;

use Nemesis\Core\Fluent;

class Email {
    protected $table = 'emails';
    protected $addressesTable = 'email_addresses';

    public function generateAddress() {
        $prefix = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8);
        $email = $prefix . '@sub.doyalbababd.com';

        Fluent::table($this->addressesTable)->insert(['email_address' => $email]);
        return $email;
    }

    public function saveEmail($recipient, $subject, $message) {
        Fluent::table($this->table)->insert([
            'recipient_email' => $recipient,
            'subject' => $subject,
            'message' => $message,
        ]);
    }

    public function getEmails($recipient) {
        return Fluent::table($this->table)
                     ->whereGET('recipient_email', '=', $recipient)
                     ->get();
    }

    public function getAllEmails() {
        return Fluent::table($this->table)
                     ->get();  // No WHERE condition, so it fetches all records
    }

    public function deleteEmail($id) {
        Fluent::table($this->table)
              ->whereDELETE('id', '=', $id)
              ->delete();
    }

    public function saveRandomEmail($email) {
        // Save the email in the 'email_addresses' table
        Fluent::table($this->addressesTable)->insert(['email_address' => $email]);
    }
}
