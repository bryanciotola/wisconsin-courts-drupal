// Fail the build on Sass errors: @uswds/compile otherwise logs and swallows them.
const fs = require('node:fs');
const path = require('node:path');
const sass = require('sass');

function entries(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((item) => {
    const file = path.join(dir, item.name);
    if (item.isDirectory()) return entries(file);
    return item.name.endsWith('.scss') && !item.name.startsWith('_')
      ? [file]
      : [];
  });
}

for (const file of entries('sass')) {
  sass.compile(file, {
    loadPaths: ['sass', 'node_modules/@uswds/uswds/packages'],
    quietDeps: true,
    silenceDeprecations: ['import', 'global-builtin'],
  });
  console.log('Sass OK:', file);
}
