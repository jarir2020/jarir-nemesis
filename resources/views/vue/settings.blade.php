@extends($layout ?? 'layouts.app')

@section('content')
<section class="frontend-shell vue-shell">
    <h1>Vue Settings</h1>
    <p>Framework: {{ $framework }}</p>
</section>
@endsection
