/** @type {import('tailwindcss').Config} */
module.exports = {
  corePlugins: { preflight: false },
  content: ["./template/*.php", "*.php"],
  theme: {
    extend: {
      colors: {
        primaryColor: "#1d1d1d",
        seconderyColor: "#34f1a3",
      },
      margin: {
        "30px": "30px",
      },
    },
  },
  plugins: [],
};
