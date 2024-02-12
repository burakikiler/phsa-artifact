module.exports = {
  content: [
    './src/**/*.js',
    './templates/**/*.{html,twig}',
    './patterns/**/*.{twig,js}',
    './components/**/*.{twig,js}',
  ],
  theme: {
    colors: {
      transparent: 'transparent',
      current: 'currentColor',
      white: '#FFFFFF',
      black: '#000000',
      primary: '#007DC5',
      secondary: '#20BEC6',
      tertiary: '#9ACA32',
      gray: '#696A6D',
    },
    extend: {
      fontSize: {
        xxs   : ['0.625rem', '0.75rem'],
        // xs    : ['0.75rem', '1rem'], // included in TW code
        // sm    : ['0.875rem', '1.125rem'], // included in TW code
        md    : ['1rem', '1.5rem'],
        // lg    : ['1.125rem', '1.5rem'],  // included in TW code
        // xl    : ['1.25rem', '1.5rem'],  // included in TW code
        // '2xl' : ['1.5rem', '2rem'],  // included in TW code
        // '3xl' : ['1.875rem', '2.5rem'],  // included in TW code
        // '4xl' : ['2.25rem', '2.5rem'],  // included in TW code
        // '5xl' : ['3rem', '3.5rem'],  // included in TW code
      },
      fontFamily: {
        title:  ['Klavika', 'sans-serif'],
        body:   ['Arial', 'sans-serif'],
      },
      minWidth: {
      },
      spacing: {
      },
      textDecoration: ['focus-visible'],
    },
    screens: {
      xs      : '576px',
      sm      : '667px',
      md      : '768px',
      lg      : '992px',
      xl      : '1200px',
      '2xl'   : '1440px',
      '3xl'   : '1600px',
    },
  },
  corePlugins: {
    container : false,
    preflight : false,
  },
  /* https://tailwindcss.com/docs/content-configuration#safelisting-classes */
  safelist: [],
};
