@if ($topUps->isNotEmpty())
    <div class="rounded-2xl border border-gray-700 bg-gray-800 p-6 shadow-sm">
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
                            <label class="flex cursor-pointer items-center gap-4 rounded-xl border border-gray-700 bg-gray-800 p-4 has-[:checked]:border-indigo-400 has-[:checked]:ring-2 has-[:checked]:ring-indigo-400">
                                <input type="radio" name="top_up_code" value="{{ $topUp->code }}" class="h-4 w-4 border-gray-600 bg-gray-700 text-indigo-500 focus:ring-indigo-400" @checked($loop->first) required>
                                <span class="flex-1">
                                    <span class="block text-lg font-semibold text-white">{{ $topUp->name }}</span>
                                    @if ($topUp->description)
                                        <span class="block text-sm text-gray-400">{{ $topUp->description }}</span>
                                    @endif
                                </span>
                                @if ($topUp->formatted_price)
                                    <span class="text-base font-semibold text-white">{{ $topUp->formatted_price }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button type="{{ $successUrl === '#' ? 'button' : 'submit' }}" class="mt-5 w-full block w-full rounded-lg bg-indigo-500 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-indigo-400">
                    {{ $buyLabel }}
                </button>
            </form>
        @else
            <ul class="space-y-3">
                @foreach ($topUps as $topUp)
                    <li class="flex items-center justify-between gap-4">
                        <span class="text-lg font-semibold text-white">{{ $topUp->name }}</span>
                        @if ($topUp->formatted_price)
                            <span class="font-semibold text-sm text-gray-400">{{ $topUp->formatted_price }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <a href="{{ $guestUrl }}" class="mt-5 block w-full rounded-lg bg-indigo-500 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-indigo-400">
                {{ __('subkit::messages.top_up.create_account_to_buy') }}
            </a>
        @endauth
    </div>
@endif
