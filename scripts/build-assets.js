#!/usr/bin/env node
/**
 * Copies front-end dependencies from node_modules into the theme's static
 * directories.  Run via `npm run build` after `npm install`.
 */

const fs            = require('fs');
const path          = require('path');
const { execSync }  = require('child_process');

const root    = path.join(__dirname, '..');
const vendor  = path.join(root, 'htdocs/themes/StateDecoded2013/static/js/vendor');
const css     = path.join(root, 'htdocs/themes/StateDecoded2013/static/css');
const fonts   = path.join(root, 'htdocs/themes/StateDecoded2013/static/fonts/font-awesome');
const nm      = path.join(root, 'node_modules');

function cp(src, dest) {
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.copyFileSync(src, dest);
    console.log(`  ${path.relative(root, dest)}`);
}

function cpDir(srcDir, destDir) {
    fs.mkdirSync(destDir, { recursive: true });
    for (const f of fs.readdirSync(srcDir)) {
        const s = path.join(srcDir, f);
        const d = path.join(destDir, f);
        if (fs.statSync(s).isFile()) { cp(s, d); }
    }
}

console.log('Building front-end assets...');

// JavaScript
cp(path.join(nm, 'jquery/dist/jquery.min.js'),                  path.join(vendor, 'jquery.min.js'));
cp(path.join(nm, 'jquery-ui-dist/jquery-ui.min.js'),            path.join(vendor, 'jquery-ui.min.js'));
cp(path.join(nm, 'mousetrap/mousetrap.min.js'),                  path.join(vendor, 'mousetrap.min.js'));
cp(path.join(nm, 'qtip2/dist/jquery.qtip.min.js'),              path.join(vendor, 'jquery.qtip.min.js'));
cp(path.join(nm, 'qtip2/dist/jquery.qtip.min.map'),             path.join(vendor, 'jquery.qtip.min.map'));

// CSS
cp(path.join(nm, 'jquery-ui-dist/jquery-ui.css'),               path.join(css, 'jquery-ui.css'));
cp(path.join(nm, 'qtip2/dist/jquery.qtip.min.css'),             path.join(css, 'jquery.qtip.min.css'));

// Font Awesome fonts (the CSS is compiled into application.css via SCSS)
cpDir(path.join(nm, 'font-awesome/fonts'), fonts);

// SCSS → CSS
const scssEntry = path.join(root, 'htdocs/themes/StateDecoded2013/static/scss/application.scss');
const cssOut    = path.join(root, 'htdocs/themes/StateDecoded2013/static/css/application.css');
const sass      = path.join(root, 'node_modules/.bin/sass');
console.log('Compiling SCSS...');
execSync(`"${sass}" "${scssEntry}" "${cssOut}" --style=compressed --no-source-map --silence-deprecation=import,slash-div,color-functions,global-builtin,function-units`, { stdio: 'inherit' });
console.log(`  ${path.relative(root, cssOut)}`);

console.log('Done.');
