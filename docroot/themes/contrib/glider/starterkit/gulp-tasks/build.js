import {
  series,
  parallel,
} from 'gulp';

import { compileSass, compileComponentStyles } from './sass';
import { lintCss }     from './lint-css';
import { compileJs }   from './js';
import { lintJs }      from './lint-js';
import makeIconsFont   from './icons';
import {
  compilePatternsSass,
  compilePatternsJs,
  lintPatternsCss,
  lintPatternsJs,
} from './patterns';
import compileJsBundles from './js-bundles';

const cssTasks = [
  makeIconsFont,
  compileSass,
  compileComponentStyles,
  lintCss,
];

const jsTasks = [
  compileJs,
  compileJsBundles,
  lintJs,
];

const patternsTasks = [
  lintPatternsCss,
  compilePatternsSass,
  lintPatternsJs,
  compilePatternsJs,
];

const build = (done) => series(makeIconsFont, parallel(jsTasks, cssTasks, patternsTasks))(done);

export default build;
