@props(['active'])

@php
$classes = ($active ?? false)
            ? 'bl-nav-link bl-nav-link-active'
            : 'bl-nav-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
