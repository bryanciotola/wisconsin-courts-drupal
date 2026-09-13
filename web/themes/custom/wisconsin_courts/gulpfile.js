const uswds = require('@uswds/compile');
const { execFile } = require('node:child_process');
const { series, watch, src, dest } = require('gulp');
const rename = require('gulp-rename');
const uglify = require('gulp-uglify-es').default;
const pkg = require('./package.json');

uswds.settings.version = 3;
uswds.paths.dist.css = pkg.paths.dist.css;
uswds.paths.dist.fonts = './assets/uswds/fonts';
uswds.paths.dist.img = './assets/uswds/images';
uswds.paths.dist.js = './assets/uswds/js';
uswds.paths.dist.components = './assets/uswds/components';
uswds.paths.src.projectSass = './sass';
// The starterkit has nested entry points. This glob preserves assets/css/base/*.
// Do not run uswds.init: dist.theme is a compilation glob, not a copy target.
uswds.paths.dist.theme = './sass/**/*';

// Keep the compiler's existing browser targets to preserve generated CSS.
// Changing browser support or USWDS versions requires a separate visual review.
function checkCss(done) {
  execFile(process.execPath, ['scripts/check-css.cjs'], (error, stdout, stderr) => {
    process.stdout.write(stdout);
    process.stderr.write(stderr);
    done(error);
  });
}

function cleanCss() {
  return import('del').then((del) => del.deleteAsync([pkg.paths.dist.css]));
}

function cleanJs() {
  return import('del').then((del) => del.deleteAsync([pkg.paths.dist.js]));
}

function buildJs() {
  // SDC component JS is loaded directly by Drupal; this is only for js/*.js.
  return src(pkg.paths.js)
    .pipe(rename({ suffix: '.min' }))
    .pipe(uglify())
    .pipe(dest(pkg.paths.dist.js));
}

// Validate before cleaning so a Sass error cannot erase the last working CSS.
const compileCss = series(checkCss, cleanCss, uswds.compileSass);
const compileJs = series(cleanJs, buildJs);
const compileAll = series(compileJs, compileCss);
// Copy over the pinned USWDS assets; do not delete source or custom assets.
const build = series(uswds.copyAssets, compileAll);

exports.compileCss = compileCss;
exports.compileJs = compileJs;
exports.compileAll = compileAll;
exports.copyUswds = uswds.copyAssets;
exports.updateUswds = build;
exports.init = (done) => done(new Error(
  'This theme is already initialized. Use npm ci and npm run build; see README.md.',
));
exports.watch = series(compileAll, () => {
  watch(pkg.paths.scss, compileCss);
  watch(pkg.paths.js, compileJs);
});
exports.default = build;
