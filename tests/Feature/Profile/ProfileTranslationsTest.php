<?php

test('profile translations exist in en', function () {
    app()->setLocale('en');

    expect(__('profile.tab_activity'))->toBe('Activity');
    expect(__('profile.subscribers'))->toBe('Subscribers');
    expect(__('profile.coming_soon'))->toBe('Coming soon');
});

test('profile translations exist in ru', function () {
    app()->setLocale('ru');

    expect(__('profile.tab_activity'))->toBe('Активность');
    expect(__('profile.subscribers'))->toBe('Подписчики');
    expect(__('profile.coming_soon'))->toBe('Скоро');
});
