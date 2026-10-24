/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                ink: '#1C1F1A',
                paper: '#F3F2ED',
                line: '#DEDBD0',
                muted: '#5B6056',
                body: '#3F433C',
                side: '#1F2A22',
                'side-on': '#3A4A34',
                'side-hover': '#2A382E',
                gold: '#C9B458',
                alert: '#9A2E17',
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
