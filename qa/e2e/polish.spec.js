// Sunday polish checks, from the demo, on each main page at 375, 390 and 430 px:
// - no subtitle, description, empty-state line or caption is cut off (an ellipsis or a clamp hiding text); only
//   names in table cells and the top bar's title may truncate, and those carry their full text;
// - no sideways scroll;
// - the GoalLeagues tab's sub-tabs (each selected) and the gold profile counts pass WCAG AA contrast;
// - screenshots of Highlights, each GoalLeagues sub-tab and both kinds of profile at iPhone size.
// POLISH_SHOTS=<label> names the screenshot folder (qa/screenshots/polish/<label>), so before and after sit side by side.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

test.use({ reducedMotion: 'reduce' });

const LABEL = process.env.POLISH_SHOTS || 'after';
const OUT = path.resolve('qa/screenshots/polish', LABEL);

/**
 * Text that's cut off: an ellipsis (text-overflow) or a line clamp actually hiding something. Allowed: the top bar
 * (its title repeats the page's own heading), table cells, and anything inside a horizontal scroller.
 */
async function clipped(page) {
    return page.evaluate(() => [...document.querySelectorAll('main *, [data-first-content] *, .gl-field *, .gl-hero *')]
        .filter((el) => {
            if (!el.offsetParent || el.closest('#app-topbar, td, th, [data-table-scroll], .sr-only, [aria-hidden="true"], [data-allow-truncate]')) {
                return false;
            }
            const style = getComputedStyle(el);
            const ellipsis = style.textOverflow === 'ellipsis' && el.scrollWidth > el.clientWidth + 1;
            const clamp = style.webkitLineClamp !== 'none' && style.webkitLineClamp !== '' && el.scrollHeight > el.clientHeight + 1;

            return (ellipsis || clamp) && el.textContent.trim().length > 0;
        })
        .map((el) => `${el.tagName.toLowerCase()}: "${el.textContent.trim().replace(/\s+/g, ' ').slice(0, 60)}"`));
}

test('Sunday polish: nothing cut off, sub-tab colors, profile header', async ({ page }) => {
    test.setTimeout(480_000);
    fs.mkdirSync(OUT, { recursive: true });

    await page.goto('/demo', { timeout: 150_000 });
    await page.waitForURL('**/dashboard', { timeout: 150_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    await page.addInitScript(() => {
        // Keep the floating tour pill out of screenshots.
        document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-judge-tour]').forEach((el) => el.remove()));
    });

    await page.goto('/leagues');
    const league = (await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href))).find((href) => /\/leagues\/\d+$/.test(href));
    await page.goto('/friends');
    const friend = (await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href))).find((href) => /\/u\/[^/]+$/.test(href));

    const pages = [
        ['clubhouse', '/dashboard'], ['history', '/dashboard?tab=history'], ['highlights', '/highlights'],
        ['goalleagues', '/leagues?tab=leagues'], ['challenges', '/leagues?tab=challenges'], ['invites', '/leagues?tab=invites'],
        ['matchups', '/matchups'], ['matchup', `${league}/matchups`], ['standings', `${league}/standings`],
        ['my-locker', '/profile'], ['their-profile', friend], ['help', '/help'],
    ];
    const problems = [];

    for (const width of [375, 390, 430]) {
        await page.setViewportSize({ width, height: 844 });

        for (const [name, url] of pages) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
            for (const text of await clipped(page)) {
                problems.push(`${name} at ${width}px: ${text}`);
            }
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow > 0) {
                problems.push(`${name} at ${width}px: ${overflow}px sideways scroll`);
            }
            if (width === 390) {
                await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: name !== 'help' });
            }
        }
    }

    // Contrast on the coloured sub-tabs (each selected) and the profile's gold counts.
    await page.setViewportSize({ width: 390, height: 844 });
    for (const tab of ['leagues', 'challenges', 'invites']) {
        await page.goto(`/leagues?tab=${tab}`);
        const result = await new AxeBuilder({ page }).include('[data-segmented]').withRules(['color-contrast']).analyze();
        result.violations.forEach((violation) => violation.nodes.forEach((node) => problems.push(`sub-tabs (${tab}): contrast ${node.any?.[0]?.data?.contrastRatio}:1 at ${node.target.join(' ')}`)));
    }
    for (const url of ['/profile', friend]) {
        await page.goto(url);
        if (!(await page.locator('[data-profile-header]').count())) {
            problems.push(`${url}: no profile header marker`);
            continue;
        }
        const result = await new AxeBuilder({ page }).include('[data-profile-header]').withRules(['color-contrast']).analyze();
        result.violations.forEach((violation) => violation.nodes.forEach((node) => problems.push(`profile header ${url}: contrast ${node.any?.[0]?.data?.contrastRatio}:1 at ${node.target.join(' ')}`)));
    }

    fs.writeFileSync(path.join(OUT, 'problems.json'), JSON.stringify([...new Set(problems)], null, 2));
    expect([...new Set(problems)]).toEqual([]);
});
