<?php
declare(strict_types=1);
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

    // Added: 2026-04-03

    /**
     * Verify the current user's password then delete a record.
     *
     * Returns a JSON 403 on wrong password, 404 when record not found,
     * or 200 with a success message on deletion.
     *
     * The model class must support a `forCompany(int $id)` scope and SoftDeletes
     * or a hard delete — whatever the model's delete() does.
     *
     * Usage in a controller method:
     *   return $this->passwordProtectedDelete($request, Invoice::class, $companyId, $invoiceId);
     */
    protected function passwordProtectedDelete(
        \Nemesis\Http\Request $request,
        string                $modelClass,
        int                   $companyId,
        int                   $id,
    ): mixed {
        $user = $request->getAttribute('auth.user');

        if (!$user || !password_verify(
            (string) $request->input('password', ''),
            (string) ($user->password ?? '')
        )) {
            http_response_code(403);
            return json_encode(['message' => 'Incorrect password.']);
        }

        $record = method_exists($modelClass, 'forCompany')
            ? $modelClass::forCompany($companyId)->where('id', $id)->first()
            : $modelClass::where('id', $id)->where('company_id', $companyId)->first();

        if (!$record) {
            http_response_code(404);
            return json_encode(['message' => 'Not found.']);
        }

        $record->delete();
        http_response_code(200);
        return json_encode(['message' => 'Deleted successfully.']);
    }
}
