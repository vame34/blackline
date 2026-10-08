    <section class="glass-panel w-full p-8">
        <div class="mb-8 flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
            <div>
                <h2 class="heading-italic mb-1 text-2xl uppercase text-white">{{ __('ui.api_tokens.title') }}</h2>
                <p class="max-w-2xl font-mono text-sm text-gray-500">{{ __('ui.api_tokens.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('api.docs') }}" target="_blank" class="skew-btn border border-[#00ff88] px-4 py-2 font-mono text-xs uppercase tracking-wider text-[#00ff88] hover:bg-[#00ff88]/10 hover:shadow-[0_0_10px_rgba(0,255,136,0.2)]">
                    <span class="skew-btn-content flex items-center gap-2">
                        <i class="ph ph-book-open"></i> {{ __('ui.api_tokens.docs') }}
                    </span>
                </a>
                <a href="{{ route('api.openapi') }}" target="_blank" class="skew-btn border border-white/20 px-4 py-2 font-mono text-xs uppercase tracking-wider text-gray-300 hover:border-white/40 hover:bg-white/5">
                    <span class="skew-btn-content flex items-center gap-2">
                        <i class="ph ph-file-code"></i> {{ __('ui.api_tokens.openapi') }}
                    </span>
                </a>
            </div>
        </div>

        <form wire:submit="createApiToken" class="mb-8 border border-white/10 bg-[#0a0a0a] p-6">
            <div class="grid grid-cols-1 items-end gap-6 md:grid-cols-12">
                <div class="space-y-2 md:col-span-5">
                    <label for="token-name" class="block font-mono text-xs font-bold uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.form.name') }}</label>
                    <input
                        id="token-name"
                        name="token_name"
                        type="text"
                        wire:model.defer="apiTokenName"
                        class="w-full px-4 py-3 font-mono text-sm text-white focus:shadow-[0_0_10px_rgba(0,255,136,0.2)]"
                    >
                    @error('apiTokenName') <div class="text-xs text-red-400">{{ $message }}</div> @enderror
                </div>
                <div class="space-y-2 md:col-span-2">
                    <label for="token-ttl" class="block font-mono text-xs font-bold uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.form.ttl') }}</label>
                    <input
                        id="token-ttl"
                        name="token_ttl"
                        type="number"
                        min="1"
                        max="3650"
                        wire:model.defer="apiTokenExpiresDays"
                        class="w-full px-4 py-3 font-mono text-sm text-white focus:shadow-[0_0_10px_rgba(0,255,136,0.2)]"
                    >
                    @error('apiTokenExpiresDays') <div class="text-xs text-red-400">{{ $message }}</div> @enderror
                </div>
                <div class="space-y-2 md:col-span-3">
                    <label for="token-trusted-ip" class="block font-mono text-xs font-bold uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.form.trusted_ip') }}</label>
                    <input
                        id="token-trusted-ip"
                        name="token_trusted_ip"
                        type="text"
                        wire:model.defer="apiTokenTrustedIp"
                        placeholder="203.0.113.10"
                        class="w-full px-4 py-3 font-mono text-sm text-white focus:shadow-[0_0_10px_rgba(0,255,136,0.2)]"
                    >
                    @error('apiTokenTrustedIp') <div class="text-xs text-red-400">{{ $message }}</div> @enderror
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="flex h-[46px] w-full items-center justify-center skew-btn bg-[#00ff88] font-bold uppercase tracking-wider text-black hover:bg-[#33ff99]">
                        <span class="skew-btn-content">{{ __('ui.api_tokens.form.create') }}</span>
                    </button>
                </div>
            </div>
        </form>

        @if ($newApiTokenPlainText)
            <div class="mb-6 border border-[#00ff88]/30 bg-[#00ff88]/10 px-4 py-3 text-sm text-[#00ff88]">
                {{ __('ui.api_tokens.token_once') }}
                <div class="mt-2 break-all font-mono text-xs text-white">{{ $newApiTokenPlainText }}</div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="px-4 py-4 font-mono text-xs font-medium uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.table.name') }}</th>
                        <th class="px-4 py-4 font-mono text-xs font-medium uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.table.created') }}</th>
                        <th class="px-4 py-4 font-mono text-xs font-medium uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.table.used') }}</th>
                        <th class="px-4 py-4 font-mono text-xs font-medium uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.table.expires') }}</th>
                        <th class="px-4 py-4 font-mono text-xs font-medium uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.table.trusted_ip') }}</th>
                        <th class="px-4 py-4 font-mono text-xs font-medium uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.table.status') }}</th>
                        <th class="px-4 py-4 text-right font-mono text-xs font-medium uppercase tracking-widest text-gray-500">{{ __('ui.api_tokens.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="font-mono text-sm">
                    @forelse ($apiTokens as $token)
                        @php
                            $isActive = $token->isActive();
                            $isExpired = $token->isExpired();
                            $statusKey = $isExpired ? 'expired' : ($isActive ? 'active' : 'revoked');
                            $statusLabel = __('ui.api_tokens.status.'.$statusKey);
                            $statusClasses = $isActive
                                ? 'bg-[#00ff88]/10 text-[#00ff88] border-[#00ff88]/20'
                                : ($isExpired ? 'bg-gray-800 text-gray-400 border-gray-700' : 'bg-gray-800 text-gray-400 border-gray-700');
                            $rowMuted = $isActive ? '' : 'opacity-60';
                        @endphp
                        <tr class="group border-b border-white/5 transition-colors hover:bg-white/[0.02] {{ $rowMuted }}">
                            <td class="px-4 py-5 font-bold text-white transition-colors group-hover:text-[#00ff88]">{{ $token->name }}</td>
                            <td class="px-4 py-5 text-gray-400" data-local-datetime data-include-year="true" data-utc-datetime="{{ $token->created_at?->toIso8601String() }}">{{ app(\App\Services\Preferences\DisplayTimezone::class)->format($token->created_at, 'd.m.Y H:i') }}</td>
                            <td class="px-4 py-5 text-gray-600" data-local-datetime data-include-year="true" data-utc-datetime="{{ $token->last_used_at?->toIso8601String() }}">{{ app(\App\Services\Preferences\DisplayTimezone::class)->format($token->last_used_at, 'd.m.Y H:i') }}</td>
                            <td class="px-4 py-5 text-gray-400" data-local-datetime data-include-year="true" data-utc-datetime="{{ $token->expires_at?->toIso8601String() }}">{{ app(\App\Services\Preferences\DisplayTimezone::class)->format($token->expires_at, 'd.m.Y H:i') }}</td>
                            <td class="px-4 py-5">
                                @if ($isActive)
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="text"
                                            wire:model.defer="apiTokenTrustedIps.{{ $token->id }}"
                                            placeholder="203.0.113.10"
                                            class="w-36 border border-white/10 bg-black/40 px-2 py-1 text-xs text-white focus:border-[#00ff88]/50 focus:outline-none"
                                        >
                                        <button
                                            type="button"
                                            wire:click="updateApiTokenTrustedIp({{ $token->id }})"
                                            class="h-7 px-2 border border-[#00ff88]/50 text-[10px] font-bold uppercase tracking-wide text-[#00ff88] hover:bg-[#00ff88]/10"
                                        >
                                            {{ __('ui.api_tokens.update_ip') }}
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-500">{{ $token->trusted_ip ?: '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-5">
                                <span class="inline-flex items-center gap-1.5 border px-2 py-1 text-xs font-bold uppercase tracking-wide {{ $statusClasses }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span> {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-5 text-right">
                                @if ($isActive)
                                    <button wire:click="revokeApiToken({{ $token->id }})" class="skew-btn skew-btn-red border border-[#ff3b3b]/50 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-[#ff3b3b] hover:border-[#ff3b3b] hover:bg-[#ff3b3b]/10 hover:shadow-[0_0_10px_rgba(255,59,59,0.3)]">
                                        <span class="skew-btn-content">{{ __('ui.api_tokens.revoke') }}</span>
                                    </button>
                                @else
                                    <span class="cursor-not-allowed text-xs font-bold uppercase tracking-wider text-gray-600">{{ __('ui.api_tokens.no_actions') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">
                                {{ __('ui.api_tokens.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex items-center justify-between text-xs font-mono text-gray-600">
            <div>{{ __('ui.api_tokens.counter', ['count' => $apiTokens->total()]) }}</div>
            <div>{{ $apiTokens->links() }}</div>
        </div>
    </section>
