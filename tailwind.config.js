import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                navy: {
                    900: '#0B1026',
                    800: '#131A3A',
                    700: '#1C2450',
                    600: '#2A3470',
                },
                vote: {
                    'blue-from': '#60A5FA',
                    'blue-to': '#2563EB',
                    'purple-from': '#C084FC',
                    'purple-to': '#7E22CE',
                },
                glow: {
                    cyan: '#22D3EE',
                },
            },
            boxShadow: {
                'vs-glow': '0 0 40px rgba(34, 211, 238, 0.35)',
                'vote-blue': '0 10px 30px -10px rgba(37, 99, 235, 0.6)',
                'vote-purple': '0 10px 30px -10px rgba(126, 34, 206, 0.6)',
            },
        },
    },

    plugins: [forms],
};
