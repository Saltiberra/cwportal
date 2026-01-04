#!/usr/bin/env node
// Puppeteer script to render a URL to PDF (A4, print background)
// Usage: node render_survey_pdf.js <url> <outputPath>

const fs = require('fs');
const puppeteer = require('puppeteer');

async function render(url, output) {
    if (!url || !output) {
        console.error('Usage: node render_survey_pdf.js <url> <output>');
        process.exit(2);
    }
    const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    const page = await browser.newPage();
    try {
        await page.goto(url, { waitUntil: 'networkidle0', timeout: 60000 });
        // Hide interactive UI and force print styles
        await page.addStyleTag({ content: '.no-print { display: none !important; } .print-button { display: none !important; }' });
        // Force A4 size
        await page.pdf({ path: output, format: 'A4', printBackground: true });
        console.log('Rendered PDF to', output);
    } catch (err) {
        console.error('Error rendering PDF:', err);
        process.exit(3);
    } finally {
        await browser.close();
    }
}

render(process.argv[2], process.argv[3]);
