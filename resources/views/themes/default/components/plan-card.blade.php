<article
    class="group relative flex flex-col overflow-hidden rounded-3xl border p-7 transition-all duration-300
        {{ $highlighted
            ? 'border-indigo-300/70 bg-white shadow-[0_16px_45px_rgba(99,102,241,0.20),0_2px_10px_rgba(15,23,42,0.06)] hover:-translate-y-1 hover:shadow-[0_22px_55px_rgba(99,102,241,0.28),0_4px_14px_rgba(15,23,42,0.08)]'
            : 'border-slate-200/80 bg-white shadow-[0_8px_30px_rgba(2,6,23,0.06),0_2px_10px_rgba(2,6,23,0.03)] hover:-translate-y-1 hover:border-indigo-200 hover:shadow-[0_14px_40px_rgba(2,6,23,0.10),0_4px_14px_rgba(2,6,23,0.05)]' }}"
>
    @if ($highlighted)
        <div class="pointer-events-none absolute -inset-px rounded-3xl bg-gradient-to-b from-indigo-400/30 via-violet-400/10 to-transparent"></div>
        <div class="pointer-events-none absolute -top-16 left-1/2 h-32 w-40 -translate-x-1/2 rounded-full bg-indigo-500/25 blur-2xl"></div>
        <div class="absolute left-1/2 top-4 -translate-x-1/2">
            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-white/90 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-indigo-700 shadow-sm">
                Most Popular
            </span>
        </div>
    @endif

    <div class="relative z-10 {{ $highlighted ? 'pt-10' : '' }} flex flex-1 flex-col">

        {{-- Plan name --}}
        <h3 class="text-lg font-semibold tracking-wide text-slate-900">{{ $plan->name }}</h3>

        {{-- Price --}}
        <div class="mt-5 flex items-end gap-2">
            <p class="text-5xl font-black leading-none tracking-[-0.02em] text-slate-900">
                {{ $plan->formatted_price }}
            </p>
            @if ($plan->price)
                <span class="pb-1 text-sm font-medium tracking-wide text-slate-500">
                    / {{ $plan->interval->value }}
                </span>
            @endif
        </div>

        {{-- Description --}}
        @if ($plan->description)
            <p class="mt-4 text-sm leading-6 tracking-[0.01em] text-slate-500">
                {{ $plan->description }}
            </p>
        @endif

        {{-- Trial badge --}}
        @if ($plan->trial_days)
            <p class="mt-3 inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold tracking-wide text-indigo-700">
                {{ $plan->trial_days }}-day free trial
            </p>
        @endif

        {{-- Feature list from plan relationship --}}
        @if ($plan->features->isNotEmpty())
            <ul class="mt-6 flex-1 space-y-3.5">
                @foreach ($plan->features as $feature)
                    <li
                        class="relative flex items-center justify-between gap-3 text-sm tracking-[0.01em] {{ $feature->pivot->is_highlighted ? 'font-semibold text-indigo-700' : 'text-slate-700' }}"
                        @if ($feature->description) x-data="{ open: false }" @endif
                    >
                        <span class="flex items-center gap-2">
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $feature->pivot->is_highlighted ? 'bg-indigo-100 text-indigo-700' : 'bg-indigo-50 text-indigo-600' }}">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.312a1 1 0 0 1-1.42-.002L3.29 9.26a1 1 0 1 1 1.42-1.406l4.041 4.08 6.543-6.598a1 1 0 0 1 1.41-.045Z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        {{ $feature->name }}

                        @if ($feature->description)
                            <span
                                class="shrink-0 cursor-pointer text-slate-400 hover:text-indigo-500"
                                @mouseenter="open = true"
                                @mouseleave="open = false"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div
                                x-show="open"
                                x-cloak
                                class="absolute bottom-full right-0 mb-1 z-10 w-52 rounded-xl bg-slate-900 px-3 py-2 text-xs font-normal text-white shadow-xl"
                            >
                                {{ $feature->description }}
                                <div class="absolute right-2 top-full h-0 w-0 border-x-4 border-t-4 border-x-transparent border-t-slate-900"></div>
                            </div>
                        @endif
                        </span>
                        @if ($feature->pivot->value)
                            <span class="text-xs {{ $feature->pivot->is_highlighted ? 'text-indigo-500' : 'text-slate-400' }}">{{ $feature->pivot->value }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex-1"></div>
        @endif

        {{-- CTA --}}
        <div class="mt-8">
            @auth
                @if (!$plan->price && !$plan->providerPrice($provider))
                    {{-- Free plan --}}
                    @if (!empty($freeUrl) && $freeUrl !== '#')
                        <a href="{{ $freeUrl }}"
                           class="block w-full rounded-xl px-4 py-3 text-center text-sm font-semibold tracking-wide text-white transition-all duration-300
                               {{ $highlighted
                                   ? 'bg-gradient-to-r from-indigo-600 to-violet-600 shadow-[0_10px_24px_rgba(99,102,241,0.38)] hover:from-indigo-500 hover:to-violet-500 hover:shadow-[0_14px_28px_rgba(99,102,241,0.46)]'
                                   : 'bg-slate-900 shadow-[0_8px_20px_rgba(15,23,42,0.24)] hover:bg-slate-800 hover:shadow-[0_10px_24px_rgba(15,23,42,0.3)]' }}"
                        >
                            {{ $labels['free'] ?? 'Get Started Free' }}
                        </a>
                    @else
                        <span class="block w-full cursor-default rounded-xl px-4 py-3 text-center text-sm font-semibold tracking-wide text-slate-400">
                            {{ $labels['free'] ?? 'Get Started Free' }}
                        </span>
                    @endif
                @else
                    {{-- Paid plan → Stripe Checkout --}}
                    <form action="{{ route('subkit.checkout.redirect') }}" method="POST">
                        @csrf
                        <input type="hidden" name="plan_code"  value="{{ $plan->code }}">
                        <input type="hidden" name="provider"    value="{{ $provider }}">
                        <input type="hidden" name="success_url" value="{{ $successUrl }}">
                        <input type="hidden" name="cancel_url"  value="{{ $cancelUrl }}">
                        @if ($companyId)
                            <input type="hidden" name="company_id" value="{{ $companyId }}">
                        @endif
                        <button
                            type="{{ $successUrl === '#' ? 'button' : 'submit' }}"
                            class="w-full rounded-xl px-4 py-3 text-sm font-semibold tracking-wide text-white transition-all duration-300
                                {{ $highlighted
                                    ? 'bg-gradient-to-r from-indigo-600 to-violet-600 shadow-[0_10px_24px_rgba(99,102,241,0.38)] hover:from-indigo-500 hover:to-violet-500 hover:shadow-[0_14px_28px_rgba(99,102,241,0.46)]'
                                    : 'bg-slate-900 shadow-[0_8px_20px_rgba(15,23,42,0.24)] hover:bg-slate-800 hover:shadow-[0_10px_24px_rgba(15,23,42,0.3)]' }}"
                        >
                            {{ $labels['subscribe'] ?? 'Get Started' }}
                        </button>
                    </form>
                @endif
            @else
                {{-- Guest --}}
                <a
                    href="{{ $guestUrl }}"
                    class="block w-full rounded-xl px-4 py-3 text-center text-sm font-semibold tracking-wide text-white transition-all duration-300
                        {{ $highlighted
                            ? 'bg-gradient-to-r from-indigo-600 to-violet-600 shadow-[0_10px_24px_rgba(99,102,241,0.38)] hover:from-indigo-500 hover:to-violet-500 hover:shadow-[0_14px_28px_rgba(99,102,241,0.46)]'
                            : 'bg-slate-900 shadow-[0_8px_20px_rgba(15,23,42,0.24)] hover:bg-slate-800 hover:shadow-[0_10px_24px_rgba(15,23,42,0.3)]' }}"
                >
                    {{ $labels['guest'] ?? 'Create Account to Subscribe' }}
                </a>
            @endauth
        </div>

    </div>
</article>
