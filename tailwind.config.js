import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                black: '#050505',
                bgDark: '#0A0A0A',
                panel: '#0a0a0a',
                bg: '#050505',
                surface: '#121212',
                surfaceHighlight: '#1A1A1A',
                neon: '#00ff88',
                accent: '#2BC96D',
                accentGlow: 'rgba(43, 201, 109, 0.15)',
                textMuted: '#888888',
                textDarker: '#555555',
                subtext: '#888888',
                border: '#262626',
                danger: '#FF4D4D',
            },
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                glow: '0 0 40px -10px rgba(43, 201, 109, 0.3)',
                card: '0 20px 40px -10px rgba(0, 0, 0, 0.5)',
            },
        },
    },

    plugins: [forms],
};
