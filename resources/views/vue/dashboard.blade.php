@extends($layout ?? 'layouts.app')

@section('content')
<section class="frontend-shell vue-shell">
    <h1>Vue Dashboard</h1>
    <p>Framework: {{ $framework }}</p>
    <p>This dashboard can sit beside React login and plain Blade shells without collision.</p>
</section>
@endsection
