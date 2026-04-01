@pushOnce('styles')
    <style>[x-cloak] { display: none !important; }</style>
@endPushOnce

@pushOnce('scripts')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endPushOnce

<div
    x-data="{
        interval: '{{ $defaultInterval }}',
        intervals: @js($intervals),
        setInterval(value) { this.interval = value; },
        activeIndex() { return Math.max(0, this.intervals.indexOf(this.interval)); },
        knobStyle() {
            const count = this.intervals.length || 1;
            // Учитываем паддинг p-1 (4px слева + 4px справа = 8px)
            return `width: calc((100% - 8px) / ${count}); transform: translateX(calc(${this.activeIndex()} * 100%));`;
        }
    }"
    class="relative [font-family:Inter,Geist,ui-sans-serif,system-ui,sans-serif]"
>
    <div class="pointer-events-none absolute inset-x-0 -top-24 mx-auto h-56 w-[42rem] max-w-full rounded-full bg-indigo-500/10 blur-3xl"></div>

    {{-- Interval switcher --}}
    @if (count($intervals) > 1)
        <div class="relative z-10 mb-10 flex justify-center">
            <div class="relative inline-grid min-w-[280px] grid-flow-col auto-cols-fr rounded-2xl border border-white/60 bg-white/80 p-1 shadow-[0_8px_30px_rgb(2,6,23,0.06)] backdrop-blur-xl">

                <div
                    class="pointer-events-none absolute inset-y-1 left-1 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 shadow-[0_8px_24px_rgba(99,102,241,0.35)] transition-all duration-300 ease-out"
                    :style="knobStyle()"
                ></div>

                @foreach ($intervals as $int)
                    <button
                        type="button"
                        @click="setInterval('{{ $int }}')"
                        :class="interval === '{{ $int }}'
                            ? 'text-white'
                            : 'text-slate-600 hover:text-slate-900'"
                        class="relative z-10 rounded-xl px-6 py-2.5 text-sm font-semibold tracking-wide transition-all duration-200"
                    >
                        {{ $labels[$int] ?? ucfirst($int) }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($setDescription)
        <p class="relative z-10 mb-8 text-center tracking-[0.01em] text-slate-500">{{ $setDescription }}</p>
    @endif

    {{-- Multi-interval --}}
    @if (count($intervals) > 1)
        <div class="relative z-10">
            @foreach ($intervals as $int)
                <div
                    x-show="interval === '{{ $int }}'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2 scale-[0.99]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-200 absolute inset-0"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.99]"
                    class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
                >
                    @forelse ($plansByInterval[$int] ?? [] as $plan)
                        @include("subkit::themes.{$theme}.components.plan-card", [
                            'plan'             => $plan,
                            'highlighted'      => $highlighted[$plan->id] ?? false,
                            'companyId'        => $companyId,
                            'successUrl'       => $successUrl,
                            'cancelUrl'        => $cancelUrl,
                            'provider'         => $provider,
                            'freeUrl'          => $freeUrl,
                    'guestUrl'         => $guestUrl,
                            'labels'           => $labels,
                        ])
                    @empty
                        <p class="col-span-full py-12 text-center text-sm tracking-wide text-slate-500">
                            No plans available at the moment.
                        </p>
                    @endforelse
                </div>
            @endforeach
        </div>

    {{-- Single-interval --}}
    @else
        <div class="relative z-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($plans as $plan)
                @include("subkit::themes.{$theme}.components.plan-card", [
                    'plan'             => $plan,
                    'highlighted'      => $highlighted[$plan->id] ?? false,
                    'companyId'        => $companyId,
                    'successUrl'       => $successUrl,
                    'cancelUrl'        => $cancelUrl,
                    'provider'         => $provider,
                    'freeUrl'          => $freeUrl,
                    'guestUrl'         => $guestUrl,
                    'labels'           => $labels,
                ])
            @empty
                <p class="col-span-full py-12 text-center text-sm tracking-wide text-slate-500">
                    No plans available at the moment.
                </p>
            @endforelse
        </div>
    @endif

</div>
