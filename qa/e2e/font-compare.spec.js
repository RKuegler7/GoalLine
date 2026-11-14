// Screenshots of the main screens at 375, 390 and 430 px for the font test, with the regression checks: the Start a
// GoalLeague and Start a Challenge buttons stay on one line, profile names aren't clipped, the Standings table's key
// columns fit, nothing scrolls sideways, and text contrast meets WCAG AA. Run on purpose, once per font:
//   FONT_COMPARE=before npx playwright test qa/e2e/font-compare.spec.js --project="iPhone 14 (WebKit)"
// Pictures land in qa/font-compare/<label>/ (git-ignored); qa/font-compare/index.html puts them side by side.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const LABEL = process.env.FONT_COMPARE;
test.skip(!LABEL, 'Set FONT_COMPARE=<label> to take the font comparison screenshots.');
test.use({ reducedMotion: 'reduce' });

const ROOT = path.resolve('qa/font-compare');
const WIDTHS = [375, 390, 430];
const CLEAN = '[data-judge-tour], [data-checkin-open] { display: none !important; }';

test('Font comparison screenshots and checks', async ({ page }) => {
    test.setTimeout(600_000);
    const problems = [];

    // The landing page, logged out.
    const shots = [];
    for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 844 });
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        shots.push(await save(page, width, 'landing'));
    }

    await page.goto('/demo', { timeout: 150_000 });
    await page.waitForURL('**/dashboard', { timeout: 150_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));

    const links = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href));
    const goal = links.find((href) => /\/goals\/\d+$/.test(href));
    await page.goto('/leagues');
    const league = (await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href))).find((href) => /\/leagues\/\d+$/.test(href));
    const challenge = (await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href))).find((href) => /\/challenges\/\d+$/.test(href));

    const screens = [
        ['dashboard', '/dashboard'], ['today', '/today'], ['goal', goal], ['leagues', '/leagues'], ['league', league],
        ['matchup', `${league}/matchups`], ['standings', `${league}/standings`], ['highlights', '/highlights'], ['profile', '/profile'],
        ...(challenge ? [['challenge', challenge]] : []),
    ];

    for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 844 });

        for (const [name, url] of screens) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
            await page.addStyleTag({ content: CLEAN });

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow > 0) {
                problems.push(`${name} at ${width}px: ${overflow}px sideways scroll`);
            }

            // Start buttons on one line.
            const wrapped = await page.evaluate(() => [...document.querySelectorAll('a, button')]
                .filter((el) => /^(Start a GoalLeague|Start a Challenge)$/.test(el.textContent.trim()) && el.offsetParent)
                .filter((el) => {
                    const range = document.createRange();
                    range.selectNodeContents(el);
                    const lines = new Set([...range.getClientRects()].map((rect) => Math.round(rect.top)));

                    return lines.size > 1;
                })
                .map((el) => el.textContent.trim()));
            for (const label of wrapped) {
                problems.push(`${name} at ${width}px: "${label}" wraps onto two lines`);
            }

            if (name === 'profile') {
                const clipped = await page.evaluate(() => [...document.querySelectorAll('h1, h2')].filter((el) => el.offsetParent && el.clientWidth > 1 && !el.closest('.sr-only') && el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).textOverflow !== 'ellipsis').map((el) => el.textContent.trim().slice(0, 40)));
                clipped.forEach((text) => problems.push(`profile at ${width}px: "${text}" is clipped`));
            }

            if (name === 'standings') {
                const fits = await page.evaluate(() => {
                    const box = document.querySelector('[data-table-scroll]').getBoundingClientRect();

                    return ['total', 'record', 'line'].every((col) => document.querySelector(`th[data-col="${col}"]`).getBoundingClientRect().right <= box.right + 1);
                });
                if (!fits) {
                    problems.push(`standings at ${width}px: Stars, W-D-L and Line ★ don't all fit without swiping`);
                }
            }

            if (width === 390) {
                const contrast = await new AxeBuilder({ page }).withRules(['color-contrast']).analyze();
                contrast.violations.forEach((violation) => violation.nodes.slice(0, 3).forEach((node) => problems.push(`${name}: contrast ${node.any?.[0]?.data?.contrastRatio ?? '?'}:1 at ${node.target.join(' ')}`)));
            }

            shots.push(await save(page, width, name));
        }
    }

    fs.writeFileSync(path.join(ROOT, LABEL, 'checks.json'), JSON.stringify({ problems, shots }, null, 2));
    writeIndex();
    expect(problems, 'font regressions').toEqual([]);
});

async function save(page, width, name) {
    const folder = path.join(ROOT, LABEL, String(width));
    fs.mkdirSync(folder, { recursive: true });
    const file = path.join(folder, `${name}.png`);
    await page.screenshot({ path: file });

    return `${width}/${name}.png`;
}

/**
 * qa/font-compare/index.html: for every screen and width, the labelled runs side by side.
 */
function writeIndex() {
    const labels = fs.readdirSync(ROOT).filter((entry) => fs.existsSync(path.join(ROOT, entry, 'checks.json'))).sort();
    const shots = [...new Set(labels.flatMap((label) => JSON.parse(fs.readFileSync(path.join(ROOT, label, 'checks.json'))).shots))];
    const rows = shots.map((shot) => `<section><h2>${shot.replace('.png', '').replace('/', ' px · ')}</h2><div class="row">${labels.map((label) => `<figure><img src="${label}/${shot}" loading="lazy"><figcaption>${label}</figcaption></figure>`).join('')}</div></section>`).join('\n');
    const problems = labels.map((label) => `<h3>${label}</h3><ul>${(JSON.parse(fs.readFileSync(path.join(ROOT, label, 'checks.json'))).problems.map((p) => `<li>${p}</li>`).join('') || '<li>No problems found.</li>')}</ul>`).join('');
    fs.writeFileSync(path.join(ROOT, 'index.html'), `<!doctype html><meta charset="utf-8"><title>Font comparison</title>
<style>body{font:14px system-ui;margin:16px;background:#fafaf5;color:#1e2922}.row{display:flex;gap:16px;overflow-x:auto}figure{margin:0}img{width:300px;border:1px solid #ddd}figcaption{font-weight:600;margin-top:4px}section{margin-bottom:28px}</style>
<h1>Font comparison</h1>${problems}${rows}`);
}
