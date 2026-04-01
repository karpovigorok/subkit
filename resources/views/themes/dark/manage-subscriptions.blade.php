<div class="space-y-4">
    @if ($isGuest)
        <div class="rounded-xl border border-gray-700 bg-gray-800 shadow-sm p-6 text-center">
            <p class="text-sm text-gray-400">Please sign in to manage your subscriptions.</p>
            @if ($guestRedirectUrl)
                <a href="{{ $guestRedirectUrl }}"
                   class="mt-3 inline-block rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-400">
                    Sign in
                </a>
            @endif
        </div>
    @else
    @forelse ($subscriptions as $subscription)
        @php
            $plan = $plans[$subscription->id] ?? null;
            $nextBillingDate = $nextBillingDates[$subscription->id] ?? null;
            $badgeClass = match ($subscription->stripe_status) {
                'active'              => 'bg-green-900 text-green-400',
                'trialing'            => 'bg-indigo-900 text-indigo-400',
                'past_due', 'unpaid'  => 'bg-yellow-900 text-yellow-400',
                'paused'              => 'bg-gray-700 text-gray-400',
                'canceled'            => 'bg-red-900 text-red-400',
                default               => 'bg-gray-700 text-gray-500',
            };
            $badgeLabel = ucfirst(str_replace('_', ' ', $subscription->stripe_status));
        @endphp

        <div class="rounded-xl border border-gray-700 bg-gray-800 shadow-sm p-6">
            {{-- Header: plan name + state badge --}}
            <div class="flex items-start justify-between gap-4">
                <h3 class="text-base font-semibold text-white">
                    {{ $plan?->name ?? 'Subscription' }}
                </h3>

                <span
                    class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
                    {{ $badgeLabel }}
                </span>
            </div>

            {{-- Plan description --}}
            @if ($plan?->description)
                <p class="mt-1 text-sm text-gray-400">{{ $plan->description }}</p>
            @endif

            {{-- Period info --}}
            <div class="mt-3 space-y-1 text-sm text-gray-400">
                <p>Started <span class="font-medium text-gray-200">{{ $subscription->created_at->format('M j, Y') }}</span></p>

                @if ($subscription->onTrial() && $subscription->trial_ends_at)
                    <p>Trial ends <span
                            class="font-medium text-gray-200">{{ $subscription->trial_ends_at->format('M j, Y') }}</span>
                    </p>
                @endif

                @if ($subscription->ends_at)
                    <p>Expires <span class="font-medium text-gray-200">{{ $subscription->ends_at->format('M j, Y') }}</span>
                    </p>
                @elseif ($nextBillingDate)
                    <p>Next charge <span class="font-medium text-gray-200">{{ $nextBillingDate->format('M j, Y') }}</span>
                    </p>
                @endif
            </div>

            {{-- Actions --}}
            <div class="mt-5 flex flex-wrap gap-3">

                {{-- Cancel: show when active/trialing and not already scheduled to cancel --}}
                @if (($subscription->active() || $subscription->onTrial()) && ! $subscription->ends_at)
                    <form action="{{ route('subkit.manage.cancel', $subscription->id) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition hover:bg-gray-700">
                            Cancel plan
                        </button>
                    </form>
                @endif

                {{-- Resume: show when a cancellation is pending (on grace period) --}}
                @if ($subscription->onGracePeriod())
                    <form action="{{ route('subkit.manage.resume', $subscription->id) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-400">
                            Resume plan
                        </button>
                    </form>
                @endif

                {{-- Manage billing: Stripe billing portal --}}
                @if ($subscription->user->stripe_id)
                    <form action="{{ route('subkit.billing-portal') }}" method="POST">
                        @csrf
                        <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                        <input type="hidden" name="return_url" value="{{ $returnUrl ?: url()->current() }}">
                        <button type="submit"
                                class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition hover:bg-gray-700">
                            Manage billing
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-gray-700 bg-gray-800 shadow-sm p-6">
            <p class="py-4 text-center text-sm text-gray-400">No subscriptions found.</p>
        </div>
    @endforelse
    @endif
</div>