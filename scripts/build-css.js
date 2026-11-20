const fs = require('node:fs/promises');
const postcss = require('postcss');
const tailwindcss = require('@tailwindcss/postcss');

const inputPath = 'assets/css/tailwind.css';
const outputPath = 'assets/css/app.css';

async function buildStylesheet() {
    const input = await fs.readFile(inputPath, 'utf8');
    const result = await postcss([tailwindcss()]).process(input, {
        from: inputPath,
        to: outputPath,
    });
    await fs.writeFile(outputPath, result.css);
}

buildStylesheet().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
