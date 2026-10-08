@props(['active'])

@php
$classes = ($active ?? false)
            ? 'bl-mobile-nav-link bl-mobile-nav-link-active'
            : 'bl-mobile-nav-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
