<x-guest-layout>
    @php
        /** @var bool $isRateLimited */
        $isRateLimited = $isRateLimited ?? false;
        /** @var int $secondsUntilUnlock */
        $secondsUntilUnlock = $secondsUntilUnlock ?? 0;
    @endphp

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

            @if ($isRateLimited)
                <p class="text-sm font-medium text-red-600 dark:text-red-400">
                    {{ \App\Http\Requests\Auth\LoginRequest::LOCKOUT_MESSAGE }}
                </p>
                <p
                    id="lockout-timer"
                    data-seconds="{{ $secondsUntilUnlock }}"
                    class="text-sm text-gray-600 dark:text-gray-300"
                >
                    Попробуйте снова через {{ sprintf('%02d:%02d', intdiv($secondsUntilUnlock, 60), $secondsUntilUnlock % 60) }}
                </p>
            @endif

            @if ($isRateLimited)
                <button
                    type="submit"
                    disabled
                    class="inline-flex items-center rounded-md bg-gray-400 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white opacity-70 cursor-not-allowed dark:bg-gray-600"
                >
                    Войти
                </button>
            @else
                <x-primary-button>
                    Войти
                </x-primary-button>
            @endif

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

    @if ($isRateLimited)
        <script>
            (() => {
                const timer = document.getElementById('lockout-timer');

                if (!timer) {
                    return;
                }

                let seconds = Number.parseInt(timer.dataset.seconds ?? '0', 10);

                const render = () => {
                    const safeSeconds = Math.max(seconds, 0);
                    const minutes = Math.floor(safeSeconds / 60).toString().padStart(2, '0');
                    const secs = (safeSeconds % 60).toString().padStart(2, '0');
                    timer.textContent = `Попробуйте снова через ${minutes}:${secs}`;
                };

                render();

                const interval = window.setInterval(() => {
                    seconds -= 1;
                    render();

                    if (seconds <= 0) {
                        window.clearInterval(interval);
                        window.location.reload();
                    }
                }, 1000);
            })();
        </script>
    @endif
</x-guest-layout>
