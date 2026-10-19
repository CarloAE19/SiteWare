const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

const CSS_DIR = path.resolve(__dirname, '../assets/css');
const JS_DIR = path.resolve(__dirname, '../assets/js');

const isWatch = process.argv.includes('--watch');

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    return (bytes / 1024).toFixed(1) + ' KB';
}

async function minifyCssFile(filePath) {
    const fileName = path.basename(filePath);
    if (fileName.endsWith('.min.css')) return;

    const outPath = filePath.replace(/\.css$/i, '.min.css');
    try {
        const sourceCode = fs.readFileSync(filePath, 'utf8');
        const result = await esbuild.transform(sourceCode, {
            loader: 'css',
            minify: true,
            legalComments: 'none',
        });

        fs.writeFileSync(outPath, result.code, 'utf8');
        const origSize = Buffer.byteLength(sourceCode, 'utf8');
        const minSize = Buffer.byteLength(result.code, 'utf8');
        const savedPercent = origSize > 0 ? (((origSize - minSize) / origSize) * 100).toFixed(1) : 0;
        console.log(`[CSS] ${fileName} -> ${path.basename(outPath)} (${formatBytes(origSize)} -> ${formatBytes(minSize)}, -${savedPercent}%)`);
    } catch (err) {
        console.error(`[ERROR] Failed to minify CSS: ${fileName}`, err.message);
    }
}

async function minifyJsFile(filePath) {
    const fileName = path.basename(filePath);
    if (fileName.endsWith('.min.js')) return;

    const outPath = filePath.replace(/\.js$/i, '.min.js');
    try {
        const sourceCode = fs.readFileSync(filePath, 'utf8');
        const result = await esbuild.transform(sourceCode, {
            loader: 'js',
            minifyWhitespace: true,
            minifySyntax: true,
            minifyIdentifiers: false, // Keep function and variable names intact for global CIMS helpers
            legalComments: 'none',
            target: 'es2020',
        });

        fs.writeFileSync(outPath, result.code, 'utf8');
        const origSize = Buffer.byteLength(sourceCode, 'utf8');
        const minSize = Buffer.byteLength(result.code, 'utf8');
        const savedPercent = origSize > 0 ? (((origSize - minSize) / origSize) * 100).toFixed(1) : 0;
        console.log(`[JS]  ${fileName} -> ${path.basename(outPath)} (${formatBytes(origSize)} -> ${formatBytes(minSize)}, -${savedPercent}%)`);
    } catch (err) {
        console.error(`[ERROR] Failed to minify JS: ${fileName}`, err.message);
    }
}

async function buildAll() {
    console.log('\n--- Building Minified Assets (No Comments) ---');

    if (fs.existsSync(CSS_DIR)) {
        const cssFiles = fs.readdirSync(CSS_DIR).filter(f => f.endsWith('.css') && !f.endsWith('.min.css'));
        for (const file of cssFiles) {
            await minifyCssFile(path.join(CSS_DIR, file));
        }
    }

    if (fs.existsSync(JS_DIR)) {
        const jsFiles = fs.readdirSync(JS_DIR).filter(f => f.endsWith('.js') && !f.endsWith('.min.js'));
        for (const file of jsFiles) {
            await minifyJsFile(path.join(JS_DIR, file));
        }
    }

    console.log('--- Assets Build Complete ---\n');
}

buildAll().then(() => {
    if (isWatch) {
        console.log('Watching assets/css and assets/js for changes (Press Ctrl+C to stop)...');
        
        const watchDir = (dir, isCss) => {
            if (!fs.existsSync(dir)) return;
            fs.watch(dir, (eventType, filename) => {
                if (!filename) return;
                if (isCss && filename.endsWith('.css') && !filename.endsWith('.min.css')) {
                    setTimeout(() => minifyCssFile(path.join(dir, filename)), 50);
                } else if (!isCss && filename.endsWith('.js') && !filename.endsWith('.min.js')) {
                    setTimeout(() => minifyJsFile(path.join(dir, filename)), 50);
                }
            });
        };

        watchDir(CSS_DIR, true);
        watchDir(JS_DIR, false);
    }
});
