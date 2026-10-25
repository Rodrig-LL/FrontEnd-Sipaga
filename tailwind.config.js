/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                ink: '#17201A',
                brand: '#1B6B3F',
                'brand-dark': '#14532D',
                paper: '#F2F4EE',
                line: '#DEDBD0',
                muted: '#5B6056',
                body: '#3F433C',
                side: '#12372A',
                'side-on': '#1E5A3D',
                'side-hover': '#1A4A35',
                gold: '#E8B923',
                alert: '#A61E1E',
                link: '#1B4F86',
            },
            fontFamily: {
                // Font sistem: tidak ada unduhan font, ringan untuk laptop lama.
                sans: ['"Segoe UI"', 'Tahoma', 'Arial', 'sans-serif'],
                mono: ['Consolas', '"Courier New"', 'monospace'],
            },
        },
    },
    plugins: [],
};
