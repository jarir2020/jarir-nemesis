@extends($layout ?? 'layouts.app')

@section('content')
<section class="frontend-shell ghost-shell">
    <h1>Ghost Preview</h1>
    <p>Framework: {{ $framework }}</p>
</section>
@endsection
