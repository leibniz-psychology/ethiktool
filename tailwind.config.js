// /** @type {import('tailwindcss').Config} */
const defaultTheme = require("tailwindcss/defaultTheme");
const colors = require("tailwindcss/colors");

module.exports = {
  mode: "layers",
  content: ["./assets/**/*.js",
            "./templates/**/*.html.twig"],
  // content: ["./src/**/*.{js,jsx,ts,tsx}"],
  theme: {
    fontFamily: {
      sans: ["Open Sans", ...defaultTheme.fontFamily.sans],
      condensed: ["Open Sans Condensed", ...defaultTheme.fontFamily.sans],
      serif: ["Zilla Slab", ...defaultTheme.fontFamily.serif],
      mono: defaultTheme.fontFamily.mono,
    },
    extend: {
      colors: {
        mono: colors.stone,
        sky: colors.sky,
        "zpid-purple": {
          DEFAULT: "#352071",
          50: "#EAE5F8",
          100: "#D9D1F2",
          200: "#B9A9E7",
          300: "#9982DB",
          400: "#785AD0",
          500: "#5A37C0",
          600: "#472C98",
          700: "#352071",
          800: "#221549",
          900: "#100A21",
        },
        "zpid-green": {
          DEFAULT: "#A0B01E",
          50: "#EEF4BF",
          100: "#E9F0AA",
          200: "#DDE97E",
          300: "#D2E252",
          400: "#C7DA27",
          500: "#A0B01E",
          600: "#788417",
          700: "#51590F",
          800: "#292D08",
          900: "#020200",
        },
        "zpid-blue": {
          DEFAULT: "#0097C6",
          50: "#E5F9FF",
          100: "#D0F4FF",
          200: "#A7EAFF",
          300: "#7FE0FF",
          400: "#56D7FF",
          500: "#2DCDFF",
          600: "#00BEF9",
          700: "#0097C6",
          800: "#007093",
          900: "#004960",
        },
        "zpid-violet": {
          DEFAULT: "#D927C4",
          50: "#FBEAF9",
          100: "#F8D4F3",
          200: "#F0A9E8",
          300: "#E87EDC",
          400: "#E152D0",
          500: "#D927C4",
          600: "#AE1F9D",
          700: "#831776",
          800: "#580F4F",
          900: "#2C0828",
        },
      },
      screens: {
        xl: "1440px",
      },
      backgroundImage: {
        "header-cyan": "url('/public/images/header_cyan.jpg')",
      },
    },
  },
  plugins: [require("tailwind-scrollbar")],
}

