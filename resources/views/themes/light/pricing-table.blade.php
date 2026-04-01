@pushOnce('styles')
    <style>[x-cloak] { display: none !important; }</style>
@endPushOnce

@pushOnce('scripts')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endPushOnce

<div x-data="{ interval: '{{ $defaultInterval }}' }">

    {{-- Interval switcher — only shown when the set contains multiple intervals --}}
    @if (count($intervals) > 1)
        <div class="flex justify-center mb-8">
            <div class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50 p-1 gap-1">
                @foreach ($intervals as $int)
                    <button
                        type="button"
                        @click="interval = '{{ $int }}'"
                        :class="interval === '{{ $int }}'
                            ? 'bg-white shadow-sm text-gray-900'
                            : 'text-gray-500 hover:text-gray-700'"
                        class="px-5 py-1.5 rounded-lg text-sm font-medium transition-all"
                    >
                        {{ $labels[$int] ?? ucfirst($int) }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($setDescription)
        <p class="text-center text-gray-500 mb-8">{{ $setDescription }}</p>
    @endif

    {{-- Multi-interval: one grid per interval, transitions on the grid container --}}
    @if (count($intervals) > 1)
        <div class="relative">
            @foreach ($intervals as $int)
                <div
                    x-show="interval === '{{ $int }}'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150 absolute inset-0"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 w-full"
                >
                    @foreach ($plansByInterval[$int] ?? [] as $plan)
                        @include("subkit::themes.{$theme}.components.plan-card", [
                            'plan'          => $plan,
                            'highlighted'   => $highlighted[$plan->id] ?? false,
                            'companyId'        => $companyId,
                            'successUrl'       => $successUrl,
                            'cancelUrl'        => $cancelUrl,
                            'provider'         => $provider,
                            'freeUrl'          => $freeUrl,
                            'guestUrl'         => $guestUrl,
                            'labels'           => $labels,
                        ])
                    @endforeach
                </div>
            @endforeach
        </div>

    {{-- Single interval: plain grid, no Alpine dependency --}}
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
                <p class="col-span-full text-center text-gray-500 py-12">
                    No plans available at the moment.
                </p>
            @endforelse
        </div>
    @endif

</div>
