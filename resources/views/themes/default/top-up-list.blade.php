@if ($topUps->isNotEmpty())
    <div class="rounded-3xl border border-slate-200/80 bg-white p-7 shadow-[0_8px_30px_rgba(2,6,23,0.06)]">
        @auth
            <form action="{{ route('subkit.top-ups.checkout.redirect') }}" method="POST">
                @csrf
            <input type="hidden" name="provider"    value="{{ $provider }}">
            <input type="hidden" name="success_url" value="{{ $successUrl }}">
            <input type="hidden" name="cancel_url"  value="{{ $cancelUrl }}">
            @if ($companyId)
                <input type="hidden" name="company_id" value="{{ $companyId }}">
            @endif
                <fieldset>
                    <legend class="sr-only">{{ __('subkit::messages.top_up.choose') }}</legend>

                    <div class="space-y-3">
                        @foreach ($topUps as $topUp)
                            <label class="flex cursor-pointer items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500/40 hover:border-indigo-300">
                                <input type="radio" name="top_up_code" value="{{ $topUp->code }}" class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($loop->first) required>
                                <span class="flex-1">
                                    <span class="block text-lg font-semibold tracking-wide text-slate-900">{{ $topUp->name }}</span>
                                    @if ($topUp->description)
                                        <span class="block text-sm text-slate-500">{{ $topUp->description }}</span>
                                    @endif
                                </span>
                                @if ($topUp->formatted_price)
                                    <span class="text-base font-semibold text-slate-900">{{ $topUp->formatted_price }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button type="{{ $successUrl === '#' ? 'button' : 'submit' }}" class="mt-5 w-full block w-full rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-semibold tracking-wide text-white shadow-[0_8px_20px_rgba(15,23,42,0.24)] transition-all duration-300 hover:bg-slate-800">
                    {{ $buyLabel }}
                </button>
            </form>
        @else
            <ul class="space-y-3">
                @foreach ($topUps as $topUp)
                    <li class="flex items-center justify-between gap-4">
                        <span class="text-lg font-semibold tracking-wide text-slate-900">{{ $topUp->name }}</span>
                        @if ($topUp->formatted_price)
                            <span class="font-semibold text-sm text-slate-500">{{ $topUp->formatted_price }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <a href="{{ $guestUrl }}" class="mt-5 block w-full rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-semibold tracking-wide text-white shadow-[0_8px_20px_rgba(15,23,42,0.24)] transition-all duration-300 hover:bg-slate-800">
                {{ __('subkit::messages.top_up.create_account_to_buy') }}
            </a>
        @endauth
    </div>
@endif
