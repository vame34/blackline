@extends('layouts.terminal')
@section('content')
<main class="mx-auto max-w-5xl space-y-6 px-6 pb-16 pt-32">
    <h1 class="text-4xl font-black italic text-white">API</h1>
    <p class="text-gray-400">{{ __('ui.project.api_help') }}</p>
    <pre class="overflow-x-auto rounded border border-white/10 bg-[#0a0a0a] p-5 font-mono text-sm text-[#00ff88]">Authorization: Bearer &lt;token&gt;
{{ url('/api/v1') }}</pre>
    <div class="space-y-3 font-mono text-sm text-white">
        @foreach(['me', 'instruments', 'signals', 'signals/{id}', 'signals/stream', 'risk-model', 'risk-model/version'] as $endpoint)
            <div class="border border-white/10 bg-[#0a0a0a] p-4">GET /api/v1/{{ $endpoint }}</div>
        @endforeach
    </div>
    @if(auth()->check() && !auth()->user()->isBanned() && auth()->user()->hasVerifiedEmail())
        <livewire:api.tokens />
    @else
        <a href="{{ route('login') }}" class="block text-[#00ff88]">{{ __('ui.api_tokens.login_required') }}</a>
    @endif
    <a href="{{ route('api.openapi') }}" class="inline-block text-[#00ff88]">OpenAPI YAML</a>
</main>
@endsection
