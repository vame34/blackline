<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-full border border-danger/40 bg-danger/20 px-5 py-2.5 text-sm font-semibold text-danger transition-colors hover:bg-danger/30']) }}>
    {{ $slot }}
</button>
