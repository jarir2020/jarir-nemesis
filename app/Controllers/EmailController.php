<?php

namespace App\Controllers;

use Nemesis\Helpers\Helpers;
use App\Models\Email;
use Nemesis\Core\Controller;
use JarirAhmed\HTTPResponse\HTTPResponse;

class EmailController extends Controller {

    private $emailModel;

    private function getRandomUserAgent() {
    $userAgents = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/92.0.902.84 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36",
        "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:85.0) Gecko/20100101 Firefox/85.0",
        "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:55.0) Gecko/20100101 Firefox/55.0",
        "Mozilla/5.0 (Linux; Android 10; Pixel 3 XL Build/QQ1A.200205.002) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Mobile Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.141 Safari/537.36",
        "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36",
        "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0"
    ];

    return $userAgents[array_rand($userAgents)]; 
    }

    public function __construct() {
        $this->emailModel = new Email();
    }

    public function getGeneratedEmails(){

        Helpers::json(['error' => true, 'message' => 'Under Construction']);
        return;
    }
  public function saveEmails() {
    session_start(); 

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $recipient = $data['recipient'] ?? null;
    } elseif ($method === 'GET') {
        $recipient = $_GET['recipient'] ?? null;
    } else {
        Helpers::json(['error' => true, 'message' => 'Invalid request method.']);
        return;
    }

    if (!$recipient) {
        Helpers::json(['error' => true, 'message' => 'Recipient email is required.']);
        return;
    }

    if (empty($_SESSION['cookie_file']) || !file_exists($_SESSION['cookie_file'])) {
        Helpers::json(['error' => true, 'message' => 'No cookie file found. Please generate an email first.']);
        return;
    }

    $cookieFilename = $_SESSION['cookie_file'];
    $cookies = file_get_contents($cookieFilename);

    $seq = $recipient;
    $maxAttempts = 30;
    $attempt = 0;
    $allEmails = [];

    while ($attempt < $maxAttempts) {
        $url = "https://api.guerrillamail.com/ajax.php?f=get_older_list&seq={$seq}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 OPR/77.0.4054.90',
            'Accept: application/json',
            'Cookie: ' . $cookies, // Attach cookies
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            error_log("cURL Error: " . curl_error($ch));
            break;
        }

        curl_close($ch);

        $emailsData = json_decode($response, true);
        if (!isset($emailsData['list']) || empty($emailsData['list'])) {
            break; // No more emails
        }

        foreach ($emailsData['list'] as $email) {
            $allEmails[] = [
                'subject' => htmlspecialchars_decode($email['mail_subject']),
                'from' => $email['mail_from'],
                'message' => $email['mail_excerpt'],
                'date' => date("Y-m-d H:i:s", $email['mail_timestamp']),
            ];

            $this->emailModel->saveEmail($recipient, $email['mail_subject'], $email['mail_excerpt']);
        }

        $seq = end($emailsData['list'])['mail_id'];
        $attempt++;
        sleep(2);
    }

    if (empty($allEmails)) {
        Helpers::json(['error' => true, 'message' => 'No emails found for this recipient.']);
    } else {
        Helpers::json(['success' => true, 'emails' => $allEmails]);
    }
}


 
    public function getOldEmails() {
        // Get input data (recipient email)
        $data = Helpers::getInput();
        $recipient = $data['recipient'] ?? null;

       
        if (!$recipient) {
            echo Helpers::json(['error' => true, 'message' => 'Recipient email is required.']);
            exit;
        }

        
        $emails = $this->emailModel->getEmails($recipient);

   
        echo Helpers::json(['success' => true, 'emails' => $emails]);
    }

   
    private $guerrillaMailAPIUrl = 'https://api.guerrillamail.com/ajax.php';

    
    public function generate() {
    session_start(); // Start the session

    $url = 'https://api.guerrillamail.com/ajax.php?f=get_email_address&ip=127.0.0.1&agent=Mzilla_foo_bar';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Capture headers (including cookies)
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);

    if ($response === false) {
        Helpers::json(['error' => true, 'message' => 'Failed to generate email.']);
        return;
    }

    
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
    $cookies = implode('; ', $matches[1]);


    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);

    curl_close($ch);

    
    $data = json_decode($body, true);
    if (isset($data['email_addr'])) {
        $email = $data['email_addr'];

        $emailModel = new \App\Models\Email();
        $emailModel->saveRandomEmail($email);
        $cookieDir = Helpers::storagePath('cookies/');

      
        if (!is_dir($cookieDir)) {
         mkdir($cookieDir, 0777, true);
        }

        $cookieFilename = $cookieDir . md5($email) . '_cookie.txt';

        file_put_contents($cookieFilename, $cookies);

        $_SESSION['cookie_file'] = $cookieFilename;

        Helpers::json(['success' => true, 'email' => $email]);
    } else {
        Helpers::json(['error' => true, 'message' => 'Failed to generate email.']);
    }
}


    private function makeApiRequest($url) {
        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Execute cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if(curl_errno($ch)) {
            HTTPResponse::badRequest();
            Helpers::json([
                'error' => true,
                'message' => 'cURL Error: ' . curl_error($ch),
            ]);
            exit;
        }

        // Close cURL session
        curl_close($ch);

        // Decode the JSON response
        return json_decode($response, true);
    }
    // View received emails for the generated email address

public function view() {
    // Get input data from the JSON body
    $data = Helpers::getInput();
    $recipient = $data['recipient'] ?? null;

    // Validate that recipient is provided
    if (!$recipient) {
        HTTPResponse::badRequest();
        Helpers::json([
            'error' => true,
            'message' => 'Recipient email is required.',
        ]);
        exit;
    }

    // Fetch emails for the recipient
    $emails = $this->fetchEmails($recipient);

    if (empty($emails)) {
        Helpers::json([
            'error' => true,
            'message' => 'No emails found for this recipient.',
        ]);
        exit;
    }

    // Return emails in the response
    Helpers::json([
        'success' => true,
        'emails' => $emails
    ]);
}



    // Optional: Delete an email by ID (GuerrillaMail doesn't directly support email deletion, this is a placeholder)
    public function delete($id) {
        // Placeholder logic for deleting an email
        // GuerrillaMail doesn't allow direct email deletion via API, so you would handle this as needed
        Helpers::json(['success' => true, 'message' => 'Email deletion functionality is a placeholder.']);
    }

    // Generate random email address using GuerrillaMail API
    private function generateRandomEmail() {
        $url = $this->guerrillaMailAPIUrl . '?f=generateEmailAddress';
        $response = file_get_contents($url);
        $emailData = json_decode($response, true);

        return $emailData['email_addr'] ?? null;
    }

public function fetchSaveEmails($recipient) {
    $seq = $recipient;  // Starting with the sequence number (must be provided when the address is created)
    $attempt = 0;
    $maxAttempts = 30;  // Maximum number of attempts to check for new emails
    $allEmails = [];

    while ($attempt < $maxAttempts) {
        // Use get_older_list instead of check_email
        $url = "https://api.guerrillamail.com/ajax.php?f=get_older_list&seq={$seq}";
        $response = file_get_contents($url);
        $emailsData = json_decode($response, true);

        // Debugging - Check response
        if (!$response) {
            // Handle empty response or error
            error_log("Error: Failed to fetch response from Guerrilla Mail API.");
        }
        
        // Debugging - Check email data structure
        //var_dump($emailsData);

        if (empty($emailsData['list'])) {
            // No more emails, break the loop
            break;
        }

        // Process the fetched emails
        foreach ($emailsData['list'] as $email) {
            $allEmails[] = [
                'subject' => htmlspecialchars_decode($email['mail_subject']),
                'from' => $email['mail_from'],
                'message' => $email['mail_excerpt'],
                'date' => date("Y-m-d H:i:s", $email['mail_timestamp'])
            ];
        }

        // Update seq to the mail_id of the last email in the list for the next call
        $seq = $emailsData['list'][count($emailsData['list']) - 1]['mail_id'];

        $attempt++;
        sleep(2);  // Retry after waiting 2 seconds
    }

    // Return the emails to the calling function
    return $allEmails;
}



private function saveEmailsToDatabase($emails) {
    $savedEmails = [];

    foreach ($emails as $email) {
        // Assuming your model method `saveEmail` is used to save each email
        $this->emailModel->saveEmail($email['mail_from'], $email['mail_subject'], $email['mail_body']);
        $savedEmails[] = [
            'subject' => $email['mail_subject'],
            'from' => $email['mail_from'],
            'message' => $email['mail_body'],
            'date' => $email['mail_date']
        ];
    }

    return $savedEmails;
}

public function searchEmailAddress() {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email_address'] ?? null;

    if (!$email) {
        Helpers::json(['error' => true, 'message' => 'Email address is required.']);
        return;
    }

    // Use the model method to search for the email address
    $emailModel = new \App\Models\Email();
    $result = $emailModel->searchByEmailAddress($email);

    if ($result) {
        Helpers::json(['success' => true, 'data' => $result]);
    } else {
        Helpers::json(['error' => true, 'message' => 'Email address not found.']);
    }
}

public function searchEmailsByRecipient() {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email_address'] ?? null;

    if (!$email) {
        Helpers::json(['error' => true, 'message' => 'Recipient email address is required.']);
        return;
    }

    // Use the model method to search for emails by recipient
    $emailModel = new \App\Models\Email();
    $results = $emailModel->searchByRecipientEmail($email);

    if (!empty($results)) {
        Helpers::json(['success' => true, 'data' => $results]);
    } else {
        Helpers::json(['error' => true, 'message' => 'No emails found for the provided address.']);
    }
}

}
