@extends($layout ?? 'layouts.app')

@section('content')
<section class="admin-shell">
    <h1>Admin Dashboard</h1>
    <p>Framework: {{ $framework ?? 'server' }}</p>
    <p>Manage users, settings, and admin-only workflows from the shared shell.</p>
</section>
@endsection
