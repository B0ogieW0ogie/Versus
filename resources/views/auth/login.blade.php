<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
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
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 space-y-3">
            @if (Route::has('password.request'))
                <a class="inline-block underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}">
                    Забыли пароль?
                </a>
            @endif

            <div>
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('register') }}">
                    Нет аккаунта? Зарегистрироваться
                </a>
            </div>

            <x-primary-button>
                Войти
            </x-primary-button>

            @if (Route::has('google.redirect'))
                <a
                    href="{{ route('google.redirect') }}"
                    class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-800"
                >
                    Войти через Google
                </a>
            @else
                <button
                    type="button"
                    disabled
                    class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                >
                    Войти через Google
                </button>
            @endif
        </div>
    </form>
</x-guest-layout>
