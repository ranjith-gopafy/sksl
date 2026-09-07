/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/views/**/*.php",
    "./public/**/*.php",
    "./public/js/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          navy: '#075183',
          blue: '#075183',
          cerulean: '#4A9CC0',
          cyan: '#4A9CC0',
          gold: '#D6981E',
          orange: '#BF5B2B',
          platinum: '#D9DBDA',
        }
      }
    }
  },
  plugins: []
}
