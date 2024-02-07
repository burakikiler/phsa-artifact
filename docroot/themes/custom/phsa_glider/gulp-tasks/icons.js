import { mkdirSync, existsSync, rmSync } from 'fs';
import { generateFonts } from 'fantasticon';

import {
  paths,
  iconFontOptions,
} from './options';

const SVGFixer = require('oslllo-svg-fixer');

const fixerOptions = {
  showProgressBar: true,
  throwIfDestinationDoesNotExist: true,
};

const makeIconsFont = (done) => {
  const {
    inputDir: destination,
    outputDir,
  } = iconFontOptions;

  if (existsSync(destination)) {
    /* Remove the icons folder each time to prevent have cached icons */
    rmSync(destination, { recursive: true, force: true });
  }
  mkdirSync(destination, { recursive: true });

  if (!existsSync(outputDir)) {
    mkdirSync(outputDir);
  }

  SVGFixer(paths.icons, destination, fixerOptions)
    .fix()
    .then(() => {
      generateFonts(iconFontOptions).then(() => {
        done();
      });
    });
};

export default makeIconsFont;
