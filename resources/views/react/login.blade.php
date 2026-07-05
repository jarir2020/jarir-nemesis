@extends($layout ?? 'layouts.app')

@section('content')
<section class="frontend-shell react-shell">
    <h1>React Login</h1>
    <p>Framework: {{ $framework }}</p>

    <form method="POST" action="/login">
        @csrf
        <label>
            Email
            <input type="email" name="email" autocomplete="email" required>
        </label>

        <label>
            Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit">Sign in</button>
    </form>
</section>
@endsection
