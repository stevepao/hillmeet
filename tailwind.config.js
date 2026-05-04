/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./views/**/*.php'],
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#0d9488',
          muted: '#ccfbf1',
        },
      },
    },
  },
};
