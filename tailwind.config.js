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
            colors: {
                primary: {
                    DEFAULT: '#c2410c',
                    hover: '#9a330a',
                    container: '#fed7aa',
                    'on-container': '#7c2d12',
                    foreground: '#ffffff',
                },
                secondary: {
                    DEFAULT: '#4f46e5',
                    hover: '#4338ca',
                    container: '#e0e7ff',
                    'on-container': '#312e81',
                    foreground: '#ffffff',
                },
                accent: {
                    DEFAULT: '#0891b2',
                    hover: '#0e7490',
                },
                surface: {
                    DEFAULT: '#ffffff',
                    alt: '#f5f5f4',
                    variant: '#e7e5e4',
                },
                text: {
                    DEFAULT: '#1c1917',
                    muted: '#57534e',
                    subtle: '#78716c',
                    inverse: '#fafaf9',
                },
                border: {
                    DEFAULT: '#e5e5e5',
                    strong: '#d6d3d1',
                },
                success: {
                    DEFAULT: '#16a34a',
                    container: '#dcfce7',
                    'on-container': '#14532d',
                },
                warning: {
                    DEFAULT: '#d97706',
                    container: '#fef3c7',
                    'on-container': '#78350f',
                },
                error: {
                    DEFAULT: '#dc2626',
                    container: '#fee2e2',
                    'on-container': '#7f1d1d',
                },
            },
            fontFamily: {
                sans: ['"Open Sans"', 'system-ui', '-apple-system', 'sans-serif'],
            },
            spacing: {
                18: '72px',
                22: '88px',
            },
            borderRadius: {
                sm: '4px',
                md: '8px',
                lg: '12px',
                xl: '16px',
                '2xl': '24px',
            },
            boxShadow: {
                'warm-sm': '0 1px 2px rgba(28, 25, 23, 0.05)',
                'warm-md': '0 4px 16px rgba(28, 25, 23, 0.10)',
                'warm-lg': '0 20px 60px rgba(28, 25, 23, 0.15)',
            },
        },
    },
    plugins: [],
};
