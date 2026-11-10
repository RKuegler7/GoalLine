// Live smoke test: `npm run smoke -- --url https://your-site`. Safe to run against production: it only uses a fresh
// one-click demo account (synthetic data, deleted on its own), and never signs in as anyone real.
//
// Steps: /health answers, the home page loads signed out, "Try the demo" opens the dashboard, a task gets checked
// off without a reload, the Matchups tab opens, and the privacy page loads. Any 500, failed page request or console
// error fails the run. Prints PASS or FAIL per step and exits non-zero on any failure.
import { chromium } from '@playwright/test';

const args = process.argv.slice(2);
const urlArg = args.find((arg) => arg.startsWith('--url='))?.slice(6) ?? args[args.indexOf('--url') + 1];

if (!urlArg || urlArg.startsWith('--')) {
    console.error('Usage: npm run smoke -- --url https://your-goalline-address');
    process.exit(2);
}

const base = new URL(urlArg).origin;
const problems = [];
const steps = [];

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const page = await context.newPage();

page.on('console', (message) => {
    if (message.type() === 'error') {
        problems.push(`Console error: ${message.text().slice(0, 200)}`);
    }
});
page.on('response', (response) => {
    if (response.status() >= 500 && new URL(response.url()).origin === base) {
        problems.push(`${response.status()} from ${new URL(response.url()).pathname}`);
    }
});
page.on('requestfailed', (request) => {
    const failure = request.failure()?.errorText ?? '';

    // Navigations away (aborted prefetches) aren't problems.
    if (new URL(request.url()).origin === base && !failure.includes('ERR_ABORTED')) {
        problems.push(`Request failed: ${new URL(request.url()).pathname} (${failure})`);
    }
});

async function step(name, run) {
    const before = problems.length;

    try {
        await run();
        steps.push([problems.length === before ? 'PASS' : 'FAIL', name]);
    } catch (error) {
        steps.push(['FAIL', `${name}: ${String(error.message).split('\n')[0]}`]);
    }
}

const ready = () => page.waitForFunction(() => document.documentElement.dataset.pageReady === '1', null, { timeout: 30_000 });

await step('Health check answers 200', async () => {
    const response = await page.request.get(`${base}/health`);
    const body = await response.json().catch(() => ({}));

    if (response.status() !== 200) {
        throw new Error(`/health returned ${response.status()} ${JSON.stringify(body.checks ?? {})}`);
    }
});

await step('Home page loads signed out', async () => {
    const response = await page.goto(`${base}/`);

    if (!response || response.status() >= 400) {
        throw new Error(`home returned ${response?.status()}`);
    }
});

await step('Try the demo opens the dashboard', async () => {
    await page.getByRole('link', { name: /Try the demo/ }).first().click();
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await ready();
});

await step('Checking in a task updates in place', async () => {
    await page.evaluate(() => {
        window.__smokeStillHere = true;
    });
    const open = page.locator('[data-region="gameplan"] button[data-check="open"]').first();

    if ((await open.count()) === 0) {
        throw new Error('no open task on the demo dashboard');
    }

    await open.click();
    await page.locator('.gl-toasts').waitFor({ timeout: 15_000 });

    if (!(await page.evaluate(() => window.__smokeStillHere === true))) {
        throw new Error('the page reloaded');
    }
});

await step('Matchups tab opens', async () => {
    await page.locator('a[data-tab]:visible', { hasText: 'Matchups' }).first().click();
    await page.waitForURL(/\/matchups|\/leagues\/\d+\/matchups/, { timeout: 30_000 });
    await ready();
    await page.locator('[data-pager]').waitFor({ timeout: 15_000 });
});

await step('Privacy page loads', async () => {
    const response = await page.goto(`${base}/privacy`);

    if (!response || response.status() >= 400) {
        throw new Error(`privacy returned ${response?.status()}`);
    }
});

await browser.close();

for (const [status, name] of steps) {
    console.log(`${status}  ${name}`);
}

const uniqueProblems = [...new Set(problems)];

for (const problem of uniqueProblems) {
    console.log(`  - ${problem}`);
}

const failed = steps.some(([status]) => status === 'FAIL') || uniqueProblems.length > 0;
console.log(failed ? `\nSmoke test FAILED on ${base}` : `\nSmoke test passed on ${base}`);
process.exit(failed ? 1 : 0);
