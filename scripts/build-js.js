const fs = require('node:fs/promises');
const path = require('node:path');

const sourceDirectory = path.resolve('node_modules/mammoth');
const outputDirectory = path.resolve('assets/js');
const files = [
    ['mammoth.browser.min.js', 'mammoth.browser.min.js'],
    ['LICENSE', 'mammoth.LICENSE'],
];

async function buildBrowserAssets() {
    await fs.mkdir(outputDirectory, { recursive: true });
    await Promise.all(files.map(([source, destination]) => (
        fs.copyFile(
            path.join(sourceDirectory, source),
            path.join(outputDirectory, destination),
        )
    )));
}

buildBrowserAssets().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
