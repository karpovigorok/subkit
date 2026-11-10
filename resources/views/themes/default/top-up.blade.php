@if ($topUp)
    <div class="rounded-3xl border border-slate-200/80 bg-white p-7 shadow-[0_8px_30px_rgba(2,6,23,0.06)]">
        <h3 class="text-lg font-semibold tracking-wide text-slate-900">{{ $topUp->name }}</h3>

        @if ($topUp->formatted_price)
            <p class="mt-3 text-4xl font-black tracking-[-0.02em] text-slate-900">
                {{ $topUp->formatted_price }}
                <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('subkit::messages.top_up.one_time') }}</span>
            </p>
        @endif

        @if ($topUp->description)
            <p class="mt-3 text-sm text-slate-500">{{ $topUp->description }}</p>
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
                    <button type="{{ $successUrl === '#' ? 'button' : 'submit' }}" class="block w-full rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-semibold tracking-wide text-white shadow-[0_8px_20px_rgba(15,23,42,0.24)] transition-all duration-300 hover:bg-slate-800">
                        {{ $buyLabel }}
                    </button>
                </form>
            @else
                <a href="{{ $guestUrl }}" class="block w-full rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-semibold tracking-wide text-white shadow-[0_8px_20px_rgba(15,23,42,0.24)] transition-all duration-300 hover:bg-slate-800">
                    {{ __('subkit::messages.top_up.create_account_to_buy') }}
                </a>
            @endauth
        </div>
    </div>
@endif
