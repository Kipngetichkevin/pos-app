{{-- Usage: @include('partials.icon', ['name' => 'home', 'class' => 'h-5 w-5']) --}}
{!! \App\Support\Icon::render($name, $class ?? 'h-5 w-5') !!}
