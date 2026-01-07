/** @type {import('tailwindcss').Config} */
module.exports = {
  corePlugins: { preflight: false },
  important: ".cgsd-tailwind",
  content: [
    "./template/**/*.php",
    "./**/*.php",
    "./dist/js/**/*.js", // ✅ เพิ่ม: สแกนไฟล์ JS ด้วย
    "./*.php",
  ],
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
