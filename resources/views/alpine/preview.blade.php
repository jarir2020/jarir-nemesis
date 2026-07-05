@extends($layout ?? 'layouts.app')

@section('content')
<section class="frontend-shell alpine-shell">
    <h1>Alpine Preview</h1>
    <p>Framework: {{ $framework }}</p>
</section>
@endsection
