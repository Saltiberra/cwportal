// tools/render_punch_list_pdf.js
// Usage: node render_punch_list_pdf.js <url> <outPath> [cookieName] [cookieValue]
const fs = require('fs');
const puppeteer = require('puppeteer');

async function run() {
    const args = process.argv.slice(2);
    if (args.length < 2) {
        console.error('Usage: node render_punch_list_pdf.js <url> <outPath> [cookieName] [cookieValue]');
        process.exit(2);
    }
    const url = args[0];
    const outPath = args[1];
    const cookieName = args[2];
    const cookieValue = args[3];

    const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    const page = await browser.newPage();

    if (cookieName && cookieValue) {
        // set cookie for localhost domain
        await page.setCookie({ name: cookieName, value: cookieValue, domain: 'localhost', path: '/' });
    }

    await page.goto(url, { waitUntil: 'networkidle0' });

    // Optionally wait a bit for animations
    await page.waitForTimeout(300);

    await page.pdf({ path: outPath, format: 'A4', printBackground: true, margin: { top: '20mm', bottom: '20mm', left: '10mm', right: '10mm' } });

    await browser.close();
    console.log('wrote', outPath);
}

run().catch(err => { console.error(err); process.exit(1); });
