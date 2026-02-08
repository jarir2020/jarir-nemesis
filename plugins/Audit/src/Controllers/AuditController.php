<?php
namespace Nemesis\Plugins\Audit\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Plugins\Audit\Models\Audit;

class AuditController extends Controller {
    public function index() {
        $audits = Audit::query()->orderBy('created_at', 'DESC')->limit(50)->get();
        return $this->render('audit::index', ['audits' => $audits]);
    }

    public function show($id) {
        $audit = Audit::find($id);
        return $this->render('audit::show', ['audit' => $audit]);
    }
}
