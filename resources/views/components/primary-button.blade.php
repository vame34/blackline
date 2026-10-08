<button {{ $attributes->merge(['type' => 'submit', 'class' => 'bl-btn-primary']) }}>
    {{ $slot }}
</button>
