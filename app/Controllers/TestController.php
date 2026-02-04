<?php
namespace App\Controllers;

use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Core\Controller;

class TestController extends Controller {
    protected $request;
    protected $mailer;

    public function __construct(Request $request, \Nemesis\Services\Mailer $mailer) {
        $this->request = $request;
        $this->mailer = $mailer;
    }

    public function index() {
        $name = $this->request->input('name', 'Stranger');

        return Response::json([
            'message' => "Hello, $name! Nemesis is working with DI.",
            'method' => $this->request->method(),
            'env_db' => getenv('DB_NAME')
        ]);
    }

    public function diTest() {
        return Response::json([
            'status' => 'DI Success',
            'request_class' => get_class($this->request),
            'mailer_class' => get_class($this->mailer)
        ]);
    }

    public function sendEmail() {
        $to = 'jarircse16@gmail.com';
        $subject = 'Nemesis Framework Test Email';
        $body = '<h1>Success!</h1><p>This is a test email from your modernized <b>Nemesis Framework</b>.</p>';
        
        $success = $this->mailer->send($to, $subject, $body);

        if ($success) {
            return Response::json(['message' => 'Email sent successfully to ' . $to]);
        }

        return Response::json([
            'error' => 'Failed to send email',
            'details' => $this->mailer->getError()
        ], 500);
    }

    public function viewTest() {
        $request = new Request();
        $name = $request->input('name', 'Jarir');
        return $this->render('test', ['name' => $name]);
    }

    public function cacheTest() {
        $key = 'test_key';
        $data = \Nemesis\Core\Cache::get($key);

        if (!$data) {
            $data = "Cached at " . date('Y-m-d H:i:s');
            \Nemesis\Core\Cache::set($key, $data, 60); // Cache for 60 seconds
            return \Nemesis\Http\Response::json(['status' => 'Cache Set', 'data' => $data]);
        }

        return \Nemesis\Http\Response::json(['status' => 'Cache Hit', 'data' => $data]);
    }
}