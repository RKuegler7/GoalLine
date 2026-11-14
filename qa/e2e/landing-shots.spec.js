// Makes the landing page's images from the real app (run on purpose, not with the QA suite):
//   LANDING_SHOTS=1 npx playwright test qa/e2e/landing-shots.spec.js --project="Desktop (Chromium)"
// - Three phone screens from a fresh demo (Matchup, Standings, plan), at 390 × 844, as 600 px wide WebP in
//   public/images/landing. The demo banner, the Judge's tour pill and the Check in button are hidden for a clean shot.
// - The link preview image (Open Graph, 1200 × 630 JPEG) from the landing page's own hero: public/images/og-goalline.jpg.
// Chromium does the resizing and WebP encoding (canvas), so no image tools are needed.
import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

test.skip(!process.env.LANDING_SHOTS, 'Set LANDING_SHOTS=1 to remake the landing page images.');
test.use({ reducedMotion: 'reduce' });

const OUT = path.resolve('public/images/landing');
const CLEAN = '[data-demo-banner], [data-judge-tour], [data-checkin-open], [data-swipe-hint] { display: none !important; }';

async function toWebp(page, png, width) {
    const dataUrl = await page.evaluate(async ({ src, width }) => {
        const image = new Image();
        image.src = src;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = Math.round(image.height * width / image.width);
        const context = canvas.getContext('2d');
        context.imageSmoothingQuality = 'high';
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/webp', 0.82);
    }, { src: `data:image/png;base64,${png.toString('base64')}`, width });

    return Buffer.from(dataUrl.split(',')[1], 'base64');
}

test('Landing page images', async ({ browser }) => {
    test.setTimeout(300_000);
    fs.mkdirSync(OUT, { recursive: true });

    const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, reducedMotion: 'reduce' });
    const page = await phone.newPage();
    await page.goto('/demo', { timeout: 150_000 });
    await page.waitForURL('**/dashboard', { timeout: 150_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));

    const goal = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((href) => /\/goals\/\d+$/.test(href)));
    await page.goto('/leagues');
    const leagues = await page.evaluate(() => [...new Set([...document.querySelectorAll('a[href]')].map((a) => a.href).filter((href) => /\/leagues\/\d+$/.test(href)))]);
    let league = leagues[0];
    for (const candidate of leagues) {
        await page.goto(`${candidate}/standings`);
        if (await page.locator('[data-form-week]').count()) {
            league = candidate;
            break;
        }
    }

    const shoot = async (name, url, scrollTo = null) => {
        await page.goto(url);
        await page.waitForLoadState('networkidle');
        await page.addStyleTag({ content: CLEAN });
        if (scrollTo) {
            await page.locator(scrollTo).first().evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 64));
        }
        await page.waitForTimeout(300);
        fs.writeFileSync(path.join(OUT, `${name}.webp`), await toWebp(page, await page.screenshot(), 600));
    };

    await shoot('matchup', `${league}/matchups`);
    await shoot('standings', `${league}/standings`);
    await shoot('plan', goal, 'ol > li[id^="goalweek-"]');
    await phone.close();

    // The link preview: the landing hero at 1200 × 630, logged out.
    const desktop = await browser.newContext({ viewport: { width: 1200, height: 630 }, reducedMotion: 'reduce' });
    const landing = await desktop.newPage();
    await landing.goto('/');
    await landing.addStyleTag({ content: '[data-landing-hero] { padding-top: 8.5rem !important; min-height: 640px; } [data-landing-hero] > .mt-7, header, .gl-splash { display: none !important; } [data-landing-hero] h1 { font-size: 4.5rem !important; }' });
    await landing.waitForTimeout(300);
    await landing.screenshot({ path: 'public/images/og-goalline.jpg', type: 'jpeg', quality: 85 });
    await desktop.close();

    for (const name of ['matchup', 'standings', 'plan']) {
        expect(fs.statSync(path.join(OUT, `${name}.webp`)).size).toBeGreaterThan(5_000);
    }
});
