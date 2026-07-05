<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Nemesis' }}</title>
    <style>
        body.app-shell {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
        }
        .app-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: rgba(15, 23, 42, 0.96);
            color: #fff;
        }
        .app-brand {
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-size: .8rem;
            opacity: .9;
        }
        .app-nav__links {
            display: flex;
            gap: .9rem;
            flex-wrap: wrap;
        }
        .app-nav a {
            color: inherit;
            text-decoration: none;
            opacity: .88;
        }
        .app-nav a:hover {
            opacity: 1;
        }
        .app-nav .spacer {
            flex: 1;
        }
        .app-nav__status {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: .9rem;
        }
        .app-nav__status form {
            margin: 0;
        }
        .app-nav__status button {
            border: 0;
            border-radius: 999px;
            padding: .45rem .85rem;
            background: #38bdf8;
            color: #082f49;
            cursor: pointer;
            font-weight: 700;
        }
        main {
            flex: 1;
            padding: 2rem 1.5rem 3rem;
        }
        .shell-badge {
            font-size: .72rem;
            text-transform: uppercase;
            opacity: .75;
            margin-left: .35rem;
        }
        .page-shell {
            max-width: 960px;
            margin: 0 auto;
            background: rgba(255,255,255,.78);
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 16px 60px rgba(15, 23, 42, .08);
            backdrop-filter: blur(14px);
        }
    </style>
</head>
<body class="app-shell">
    <nav class="app-nav">
        <div class="app-brand">Nemesis <span class="shell-badge">{{ $framework ?? 'server' }}</span></div>
        <div class="app-nav__links">
            <a href="{{ function_exists('route') ? route('dashboard.page') : '/dashboard' }}">Dashboard</a>
            <a href="{{ function_exists('route') ? route('profile.page') : '/profile' }}">Profile</a>
            <a href="{{ function_exists('route') ? route('settings.page') : '/settings' }}">Settings</a>
            @if(!empty($canSeeAdmin))
                <a href="{{ function_exists('route') ? route('admin.dashboard') : '/admin' }}">Admin</a>
            @endif
        </div>
        <span class="spacer"></span>
        <div class="app-nav__status">
            <span>{{ !empty($isAuthenticated) ? 'Signed in' : 'Guest' }}</span>
            @if(!empty($isAuthenticated))
                <form method="POST" action="{{ function_exists('route') ? route('logout') : '/logout' }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            @else
                <a href="{{ function_exists('route') ? route('login.page') : '/login' }}">Login</a>
            @endif
        </div>
    </nav>
    <main>
        <div class="page-shell">
            @yield('content')
        </div>
    </main>
</body>
</html>
