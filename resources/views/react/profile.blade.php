@extends($layout ?? 'layouts.app')

@section('content')
<section class="frontend-shell react-shell">
    <h1>React Profile</h1>
    <p>Framework: {{ $framework }}</p>
</section>
@endsection
