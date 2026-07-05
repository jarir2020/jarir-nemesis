<?php
declare(strict_types=1);

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\Request;

class FrontendController extends Controller
{
    public function login(Request $request): void
    {
        $this->render('login', $this->pageData($request));
    }

    public function admin(Request $request): void
    {
        $this->render('admin/dashboard', $this->pageData($request));
    }

    public function profile(Request $request): void
    {
        $this->render('profile', $this->pageData($request));
    }

    public function settings(Request $request): void
    {
        $this->render('settings', $this->pageData($request));
    }

    public function dashboard(Request $request): void
    {
        $this->render('dashboard', $this->pageData($request));
    }

    public function preview(Request $request, string $framework = 'server'): void
    {
        $this->render('preview', $this->pageData($request, $framework));
    }

    /**
     * Shared render context for auth-aware frontend pages.
     */
    protected function pageData(Request $request, string $fallbackFramework = 'server'): array
    {
        $framework = (string) $request->getMeta('frontend.framework', $fallbackFramework);
        $auth = $request->getMeta('auth', []);

        return [
            'framework' => $framework,
            'layout' => $request->getMeta('frontend.layout', 'layouts.app'),
            'isAuthenticated' => !empty($auth),
            'canSeeAdmin' => ($auth['role'] ?? null) === 'admin',
            'authRole' => $auth['role'] ?? null,
            'authSubject' => $auth['sub'] ?? null,
        ];
    }
}
