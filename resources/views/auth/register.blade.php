<x-guest-layout>
    @php
        /** @var string|null $referralCode */
        $referralCode = $referralCode ?? null;
    @endphp

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Пароль" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Подтверждение пароля" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="referral_code" value="Реферальный код (если есть)" />
            <x-text-input
                id="referral_code"
                class="block mt-1 w-full uppercase"
                type="text"
                name="referral_code"
                :value="old('referral_code', $referralCode)"
                autocomplete="off"
            />
            <x-input-error :messages="$errors->get('referral_code')" class="mt-2" />
        </div>

        <div class="mt-4 space-y-3">
            <a class="inline-block underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                Уже есть аккаунт? Войти
            </a>

            <x-primary-button>
                Зарегистрироваться
            </x-primary-button>

            @if (Route::has('google.redirect'))
                <a
                    href="{{ route('google.redirect') }}"
                    class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-800"
                >
                    Зарегистрироваться через Google
                </a>
            @else
                <button
                    type="button"
                    disabled
                    class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                >
                    Зарегистрироваться через Google
                </button>
            @endif
        </div>
    </form>
</x-guest-layout>
