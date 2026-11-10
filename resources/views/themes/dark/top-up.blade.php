@if ($topUp)
    <div class="rounded-2xl border border-gray-700 bg-gray-800 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-white">{{ $topUp->name }}</h3>

        @if ($topUp->formatted_price)
            <p class="mt-3 text-3xl font-bold text-white">
                {{ $topUp->formatted_price }}
                <span class="text-xs text-gray-500">{{ __('subkit::messages.top_up.one_time') }}</span>
            </p>
        @endif

        @if ($topUp->description)
            <p class="mt-3 text-sm text-gray-400">{{ $topUp->description }}</p>
        @endif

        <div class="mt-6">
            @auth
                <form action="{{ route('subkit.top-ups.checkout.redirect') }}" method="POST">
                    @csrf
                    <input type="hidden" name="top_up_code" value="{{ $topUp->code }}">
                    <input type="hidden" name="provider"    value="{{ $provider }}">
                    <input type="hidden" name="success_url" value="{{ $successUrl }}">
                    <input type="hidden" name="cancel_url"  value="{{ $cancelUrl }}">
                    @if ($companyId)
                        <input type="hidden" name="company_id" value="{{ $companyId }}">
                    @endif
                    <button type="{{ $successUrl === '#' ? 'button' : 'submit' }}" class="block w-full rounded-lg bg-indigo-500 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-indigo-400">
                        {{ $buyLabel }}
                    </button>
                </form>
            @else
                <a href="{{ $guestUrl }}" class="block w-full rounded-lg bg-indigo-500 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-indigo-400">
                    {{ __('subkit::messages.top_up.create_account_to_buy') }}
                </a>
            @endauth
        </div>
    </div>
@endif
