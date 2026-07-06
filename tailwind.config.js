/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.ts',
        './resources/**/*.tsx',
    ],
    theme: {
        extend: {
            // UNAB design tokens. Full mapping in PR 3 (T-006) when the
            // wireframes port. Placeholders here so the Tailwind config
            // is valid and the build succeeds.
            colors: {
                primary: {
                    DEFAULT: '#c2410c', // burnt orange — UNAB
                    foreground: '#ffffff',
                },
                secondary: {
                    DEFAULT: '#4f46e5', // indigo
                },
                accent: {
                    DEFAULT: '#0891b2', // cyan
                },
            },
            fontFamily: {
                sans: ['"Open Sans"', 'system-ui', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
