<div class="space-y-4 [font-family:Inter,Geist,ui-sans-serif,system-ui,sans-serif]">

    @if ($isGuest)
        <div class="rounded-3xl border border-slate-200/80 bg-white p-8 text-center shadow-[0_8px_30px_rgba(2,6,23,0.06)]">
            <p class="text-sm tracking-[0.01em] text-slate-500">Please sign in to manage your subscriptions.</p>
            @if ($guestRedirectUrl)
                <a href="{{ $guestRedirectUrl }}"
                   class="mt-4 inline-block rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold tracking-wide text-white transition-all duration-200 hover:bg-slate-800">
                    Sign in
                </a>
            @endif
        </div>

    @else
        @forelse ($subscriptions as $subscription)
            @php
                $plan           = $plans[$subscription->id] ?? null;
                $nextBillingDate = $nextBillingDates[$subscription->id] ?? null;
                $badgeClass     = match ($subscription->stripe_status) {
                    'active'              => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    'trialing'            => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                    'past_due', 'unpaid'  => 'border-amber-200 bg-amber-50 text-amber-700',
                    'paused'              => 'border-slate-200 bg-slate-50 text-slate-500',
                    'canceled'            => 'border-red-200 bg-red-50 text-red-600',
                    default               => 'border-slate-200 bg-slate-50 text-slate-400',
                };
                $badgeLabel = ucfirst(str_replace('_', ' ', $subscription->stripe_status));
            @endphp

            <div class="rounded-3xl border border-slate-200/80 bg-white p-7 shadow-[0_8px_30px_rgba(2,6,23,0.06),0_2px_10px_rgba(2,6,23,0.03)] transition-shadow hover:shadow-[0_14px_40px_rgba(2,6,23,0.10)]">

                {{-- Header --}}
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-base font-semibold tracking-wide text-slate-900">
                        {{ $plan?->name ?? 'Subscription' }}
                    </h3>
                    <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold tracking-wide {{ $badgeClass }}">
                        {{ $badgeLabel }}
                    </span>
                </div>

                {{-- Plan description --}}
                @if ($plan?->description)
                    <p class="mt-1 text-sm leading-6 tracking-[0.01em] text-slate-500">{{ $plan->description }}</p>
                @endif

                {{-- Period info --}}
                <div class="mt-4 space-y-1 text-sm tracking-[0.01em] text-slate-500">
                    <p>Started <span class="font-medium text-slate-700">{{ $subscription->created_at->format('M j, Y') }}</span></p>

                    @if ($subscription->onTrial() && $subscription->trial_ends_at)
                        <p>Trial ends <span class="font-medium text-slate-700">{{ $subscription->trial_ends_at->format('M j, Y') }}</span></p>
                    @endif

                    @if ($subscription->ends_at)
                        <p>Expires <span class="font-medium text-slate-700">{{ $subscription->ends_at->format('M j, Y') }}</span></p>
                    @elseif ($nextBillingDate)
                        <p>Next charge <span class="font-medium text-slate-700">{{ $nextBillingDate->format('M j, Y') }}</span></p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="mt-6 flex flex-wrap gap-3">

                    {{-- Cancel --}}
                    @if (($subscription->active() || $subscription->onTrial()) && ! $subscription->ends_at)
                        <form action="{{ route('subkit.manage.cancel', $subscription->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold tracking-wide text-slate-700 transition-all duration-200 hover:border-slate-300 hover:bg-slate-50">
                                Cancel plan
                            </button>
                        </form>
                    @endif

                    {{-- Resume --}}
                    @if ($subscription->onGracePeriod())
                        <form action="{{ route('subkit.manage.resume', $subscription->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-sm font-semibold tracking-wide text-white shadow-[0_6px_16px_rgba(99,102,241,0.32)] transition-all duration-200 hover:from-indigo-500 hover:to-violet-500">
                                Resume plan
                            </button>
                        </form>
                    @endif

                    {{-- Manage billing --}}
                    @if ($subscription->user->stripe_id)
                        <form action="{{ route('subkit.billing-portal') }}" method="POST">
                            @csrf
                            <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                            <input type="hidden" name="return_url" value="{{ $returnUrl ?: url()->current() }}">
                            <button type="submit"
                                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold tracking-wide text-slate-700 transition-all duration-200 hover:border-slate-300 hover:bg-slate-50">
                                Manage billing
                            </button>
                        </form>
                    @endif

                </div>
            </div>

        @empty
            <div class="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-[0_8px_30px_rgba(2,6,23,0.06)]">
                <p class="py-4 text-center text-sm tracking-[0.01em] text-slate-500">No subscriptions found.</p>
            </div>
        @endforelse
    @endif

</div>
