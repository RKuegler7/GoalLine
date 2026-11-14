// Lighthouse (mobile) on the landing page, the dashboard, a Matchup and the Standings table, in Playwright's
// Chromium with a demo session. Run on purpose:
//   LIGHTHOUSE=before npx playwright test qa/e2e/lighthouse.spec.js --project="Desktop (Chromium)"
// Scores land in qa/lighthouse/<label>.json with the full HTML reports beside them (git-ignored). Note: QA runs on
// PHP's one-request-at-a-time built-in server on this computer, so performance here is lower than on the live site.
import { chromium, expect, test } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const LABEL = process.env.LIGHTHOUSE;
test.skip(!LABEL, 'Set LIGHTHOUSE=<label> to run Lighthouse.');

const OUT = path.resolve('qa/lighthouse');
const PORT = 9333;

test('Lighthouse, mobile', async ({ baseURL }) => {
    test.setTimeout(900_000);
    const { default: lighthouse } = await import('lighthouse');
    fs.mkdirSync(OUT, { recursive: true });

    const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gl-lh-'));
    const context = await chromium.launchPersistentContext(profile, { args: [`--remote-debugging-port=${PORT}`], baseURL });
    const page = await context.newPage();

    // The landing page first, logged out.
    const pages = [['landing', `${baseURL}/`]];
    const scores = {};
    const run = async (name, url) => {
        const result = await lighthouse(url, { port: PORT, output: 'html', logLevel: 'error', disableStorageReset: true, onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'] });
        fs.writeFileSync(path.join(OUT, `${LABEL}-${name}.html`), result.report);
        const lhr = result.lhr;
        scores[name] = Object.fromEntries(Object.entries(lhr.categories).map(([key, category]) => [key, Math.round(category.score * 100)]));
        scores[name].metrics = {
            lcp: Math.round(lhr.audits['largest-contentful-paint'].numericValue),
            cls: Number(lhr.audits['cumulative-layout-shift'].numericValue.toFixed(3)),
            tbt: Math.round(lhr.audits['total-blocking-time'].numericValue),
        };
        scores[name].opportunities = Object.values(lhr.audits)
            .filter((audit) => audit.details?.type === 'opportunity' && (audit.details.overallSavingsMs ?? 0) > 100)
            .map((audit) => `${audit.title} (${Math.round(audit.details.overallSavingsMs)} ms)`);
        scores[name].failed = Object.values(lhr.audits)
            .filter((audit) => audit.score === 0 && audit.scoreDisplayMode === 'binary')
            .map((audit) => audit.title);
    };

    await run(...pages[0]);

    await page.goto('/demo', { timeout: 150_000 });
    await page.waitForURL('**/dashboard', { timeout: 150_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    await page.goto('/leagues');
    const league = (await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href))).find((href) => /\/leagues\/\d+$/.test(href));

    for (const [name, url] of [['dashboard', `${baseURL}/dashboard`], ['matchup', `${league}/matchups`], ['standings', `${league}/standings`]]) {
        await run(name, url);
    }

    await context.close();
    fs.writeFileSync(path.join(OUT, `${LABEL}.json`), JSON.stringify(scores, null, 2));
    console.log(JSON.stringify(scores, null, 2));
    expect(Object.keys(scores)).toHaveLength(4);
});
