/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{html,js,svelte,ts}'],
  theme: {
    extend: {
      colors: {
        sport: {
          blue: {
            50: '#eff6ff',
            500: '#3b82f6',
            600: '#2563eb',
            700: '#1d4ed8',
            800: '#1e40af'
          },
          green: {
            50: '#f0fdf4',
            500: '#22c55e',
            600: '#16a34a',
            700: '#15803d',
            800: '#166534'
          }
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif']
      }
    },
  },
  plugins: [require('@tailwindcss/forms')],
} 