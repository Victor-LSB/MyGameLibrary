/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./src/Views/**/*.php",
    "./public/**/*.php",
    "./public/**/*.html",
  ],
  theme: {
    extend: {
      colors: {
        // Cores customizadas do seu projeto
        'dark-academia': {
          'bg': '#0f0c0a',
          'card': '#181311',
          'gold': '#c8a96a',
          'parchment': '#e7dbc1',
          'moss': '#6e7b4f',
          'burgundy': '#6d3f34',
          'line': '#8f7b60',
        }
      },
      fontFamily: {
        sans: ['system-ui', 'sans-serif'],
      }
    },
  },
  plugins: [],
}
