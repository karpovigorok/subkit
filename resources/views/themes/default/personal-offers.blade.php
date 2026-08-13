<div class="space-y-6 [font-family:Inter,Geist,ui-sans-serif,system-ui,sans-serif]">
    @foreach ($offers as $offer)
        <article class="group relative flex flex-col overflow-hidden rounded-3xl border border-indigo-300/70 bg-white p-7 shadow-[0_16px_45px_rgba(99,102,241,0.20),0_2px_10px_rgba(15,23,42,0.06)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_22px_55px_rgba(99,102,241,0.28),0_4px_14px_rgba(15,23,42,0.08)]">

            {{-- Indigo glow overlays (same as highlighted plan-card) --}}
            <div class="pointer-events-none absolute -inset-px rounded-3xl bg-gradient-to-b from-indigo-400/30 via-violet-400/10 to-transparent"></div>
            <div class="pointer-events-none absolute -top-16 left-1/2 h-32 w-40 -translate-x-1/2 rounded-full bg-indigo-500/25 blur-2xl"></div>

            {{-- "Personal Offer" badge --}}
            <div class="absolute left-1/2 top-4 -translate-x-1/2">
                <span class="inline-flex items-center rounded-full border border-indigo-200 bg-white/90 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-indigo-700 shadow-sm">
                    Personal Offer
                </span>
            </div>

            <div class="relative z-10 flex flex-1 flex-col pt-10">

                {{-- Name + price --}}
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <h3 class="text-lg font-semibold tracking-wide text-slate-900">{{ $offer->name }}</h3>
                    <div class="shrink-0 text-right">
                        <span class="text-3xl font-black leading-none tracking-[-0.02em] text-slate-900">
                            {{ $offer->formatted_price }}
                        </span>
                        @if ($offer->price)
                            <span class="text-sm font-medium tracking-wide text-slate-500"> / {{ $offer->interval->value }}</span>
                        @endif
                    </div>
                </div>

                {{-- Description --}}
                @if ($offer->description)
                    <p class="mt-4 text-sm leading-6 tracking-[0.01em] text-slate-500">{{ $offer->description }}</p>
                @endif

                {{-- Trial badge --}}
                @if ($offer->trial_days)
                    <p class="mt-3 inline-flex self-start rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold tracking-wide text-indigo-700">
                        {{ $offer->trial_days }}-day free trial
                    </p>
                @endif

                {{-- Features --}}
                @if ($offer->features->isNotEmpty())
                    <ul class="mt-6 flex-1 space-y-3.5">
                        @foreach ($offer->features as $feature)
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
                                            class="absolute bottom-full right-0 z-10 mb-1 w-52 rounded-xl bg-slate-900 px-3 py-2 text-xs font-normal text-white shadow-xl"
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

                {{-- Limits --}}
                @if ($offer->limits->isNotEmpty())
                    <div class="mt-6">
                        <div class="flex items-center gap-3">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Limits</span>
                            <div class="h-px flex-1 bg-slate-100"></div>
                        </div>
                        <ul class="mt-3 space-y-2">
                            @foreach ($offer->limits as $limit)
                                <li class="flex items-center justify-between text-sm tracking-[0.01em]">
                                    <span class="text-slate-600">{{ \Illuminate\Support\Str::headline($limit->key) }}</span>
                                    <span class="font-medium text-slate-800">
                                        @php $val = $limit->casted_value; @endphp
                                        @if (is_bool($val))
                                            {{ $val ? 'Yes' : 'No' }}
                                        @else
                                            {{ $val }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- CTA --}}
                <div class="mt-8">
                    <form action="{{ route('subkit.checkout.redirect') }}" method="POST">
                        @csrf
                        <input type="hidden" name="plan_code"   value="{{ $offer->code }}">
                        <input type="hidden" name="provider"    value="{{ $provider }}">
                        <input type="hidden" name="success_url" value="{{ $successUrl }}">
                        <input type="hidden" name="cancel_url"  value="{{ $cancelUrl }}">
                        @if ($companyId)
                            <input type="hidden" name="company_id" value="{{ $companyId }}">
                        @endif
                        <button
                            type="submit"
                            class="w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3 text-sm font-semibold tracking-wide text-white shadow-[0_10px_24px_rgba(99,102,241,0.38)] transition-all duration-300 hover:from-indigo-500 hover:to-violet-500 hover:shadow-[0_14px_28px_rgba(99,102,241,0.46)]"
                            style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;"
                        >
                            {{ $labels['claim'] }}
                        </button>
                    </form>
                </div>

            </div>
        </article>
    @endforeach
</div>
