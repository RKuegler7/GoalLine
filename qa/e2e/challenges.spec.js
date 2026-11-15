// Challenges v2 in real browsers, from the demo:
// - find and join a public Challenge (Target: read 20 pages a day) from the GoalLeagues tab, and log a number;
// - find a public GoalLeague and join it through the usual goal step;
// - start a Challenge from the Lent template (kickoff state), a Weekly one and an Abstain one, and check in to each;
// - view the Challenge Standings and swipe them.
// Each in-app action updates in place (no full page reload), the bottom bar stays fixed, nothing scrolls sideways at
// 375, 390 and 430 px, there are no console errors, and text contrast meets WCAG AA (axe).
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.use({ reducedMotion: 'reduce' });

const shot = (testInfo, name) => `qa/screenshots/challenges-${testInfo.project.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}-${name}.png`;

function watchErrors(page) {
    const errors = [];
    const noise = /Load failed$|due to access control checks\.?$/;
    page.on('pageerror', (error) => {
        if (!noise.test(error.message)) {
            errors.push(error.message);
        }
    });
    page.on('console', (message) => {
        if (message.type() === 'error' && !noise.test(message.text())) {
            errors.push(message.text());
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 500) {
            errors.push(`${response.status()} ${response.url()}`);
        }
    });

    return errors;
}

/**
 * Run an action that must update the page in place (live.js), not reload it.
 */
async function inPlace(page, action) {
    await page.evaluate(() => {
        window.__stillHere = true;
    });
    await action();
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => window.__stillHere === true), 'updated in place, no full reload').toBe(true);
}

async function checkLayout(page, label) {
    for (const width of [375, 390, 430]) {
        await page.setViewportSize({ width, height: 844 });
        const { overflow, culprits } = await page.evaluate(() => {
            const limit = document.documentElement.clientWidth;
            const wide = [...document.querySelectorAll('body *')].filter((el) => el.getBoundingClientRect().right > limit + 1
                && !el.closest('[data-table-scroll], [class*="overflow-x"]')
                && ![...el.children].some((child) => child.getBoundingClientRect().right > limit + 1));

            return {
                overflow: document.documentElement.scrollWidth - limit,
                culprits: wide.slice(0, 4).map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 4).join('.')} "${(el.textContent || '').trim().slice(0, 30)}"`),
            };
        });
        expect(overflow, `${label}: no sideways scroll at ${width}px. Widest: ${culprits.join(' | ')}`).toBeLessThanOrEqual(0);
    }

    // The bottom bar stays put (phones only show it).
    const tabbar = page.locator('#app-tabbar');
    if (await tabbar.isVisible()) {
        expect(await tabbar.evaluate((bar) => getComputedStyle(bar).position)).toBe('fixed');
    }

    const contrast = await new AxeBuilder({ page }).withRules(['color-contrast']).analyze();
    expect(contrast.violations.flatMap((violation) => violation.nodes.map((node) => node.target.join(' '))), `${label}: contrast`).toEqual([]);
}

/**
 * Start a Challenge from a template, starting today (New York time), and land on its page.
 */
async function startFromTemplate(page, template, today) {
    await page.goto(`/challenges/create?template=${template}`);
    const start = page.locator('[data-challenge-start]');
    if (await start.isVisible()) {
        await start.fill(today);
    }
    await page.getByRole('button', { name: 'Start the Challenge' }).click();
    await page.waitForURL(/\/challenges\/\d+$/);
}

test('Challenges: build, check in by type, Standings, and public lists', async ({ page }, testInfo) => {
    test.setTimeout(420_000);
    const errors = watchErrors(page);

    await page.goto('/demo', { timeout: 150_000 });
    await page.waitForURL('**/dashboard', { timeout: 150_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    const today = await page.evaluate(() => new Date().toLocaleDateString('en-CA', { timeZone: 'America/New_York' }));

    await test.step('Find and join a public Challenge, then log a number (Target)', async () => {
        await page.goto('/leagues?tab=challenges');
        const row = page.locator('[data-public-row="challenges"]');
        await expect(row).toContainText('Read 20 pages a day');
        await checkLayout(page, 'Challenges tab');
        await page.screenshot({ path: shot(testInfo, 'public-challenges'), fullPage: true });

        await row.locator('[data-public-challenge]', { hasText: 'Read 20 pages a day' }).locator('[data-join-public-challenge]').click();
        await expect(page.locator('[data-challenge-join]')).toContainText('The rules');
        await page.getByLabel('I’m in for these rules').check();
        await page.getByRole('button', { name: 'Join the Challenge' }).click();
        await page.waitForURL(/\/challenges\/\d+$/);

        const form = page.locator('[data-challenge-checkin] form', { has: page.locator('input[name=value]') }).first();
        await form.locator('input[name=value]').fill('25');
        await inPlace(page, () => form.getByRole('button', { name: 'Save' }).click());
        await expect(page.locator('[data-challenge-checkin]').first()).toContainText('Logged 25 pages');
        await page.screenshot({ path: shot(testInfo, 'target'), fullPage: true });
    });

    await test.step('Find a public GoalLeague and join through the goal step', async () => {
        await page.goto('/leagues');
        const row = page.locator('[data-public-row="leagues"]');
        await expect(row.locator('[data-public-league]').first()).toBeVisible();
        await page.screenshot({ path: shot(testInfo, 'public-leagues'), fullPage: true });
        const name = (await row.locator('[data-public-league] p.text-club').first().textContent()).trim();
        await row.locator('[data-join-public]').first().click();
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).toContainText(name);
        expect(page.url()).toMatch(/\/(join|invite|leagues)\//);
    });

    await test.step('Lent template: Ash Wednesday to Holy Saturday, Sundays off, kickoff state', async () => {
        await page.goto('/challenges/create?template=lent');
        await expect(page.locator('[data-challenge-dates]')).toContainText(/Runs Wed, (Feb|Mar) \d+, \d{4} to Sat, (Mar|Apr) \d+, \d{4} \(46 days\)/);
        await checkLayout(page, 'Builder');
        await page.screenshot({ path: shot(testInfo, 'builder-lent'), fullPage: true });
        await page.getByRole('button', { name: 'Start the Challenge' }).click();
        await page.waitForURL(/\/challenges\/\d+$/);
        await expect(page.locator('[data-challenge]')).toContainText('Starts Wednesday');
        await expect(page.locator('[data-challenge]')).toContainText('Sundays off');
    });

    await test.step('Weekly: one tap, "1 of 4 this week"', async () => {
        await startFromTemplate(page, 'workout-4', today);
        await expect(page.locator('body')).toContainText(/day 1 of 30/i); // The template's 30 days.
        await expect(page.locator('[data-challenge]')).toContainText('Workout 4 times a week');
        await inPlace(page, () => page.getByRole('button', { name: 'I did it today' }).click());
        await expect(page.locator('[data-this-week]')).toHaveText('1 of 4 this week');
        await checkLayout(page, 'Weekly Challenge');
        await page.screenshot({ path: shot(testInfo, 'weekly'), fullPage: true });
    });

    await test.step('Abstain: stayed on track, then the Standings swipe', async () => {
        await startFromTemplate(page, 'no-social', today);
        await inPlace(page, () => page.getByRole('button', { name: 'Stayed on track' }).click());
        await expect(page.locator('[data-challenge-checkin]').first()).toContainText('On track today');
        await page.screenshot({ path: shot(testInfo, 'abstain'), fullPage: true });

        const scroller = page.locator('[data-challenge-table] [data-table-scroll]');
        await scroller.evaluate((el) => el.scrollBy({ left: 400, behavior: 'instant' }));
        await expect.poll(() => scroller.evaluate((el) => el.scrollLeft)).toBeGreaterThan(50);
        expect(await page.evaluate(() => window.scrollX)).toBe(0);
        await expect(page.locator('[data-challenge-table] th[data-col="joined"]')).toBeAttached();
        await page.screenshot({ path: shot(testInfo, 'standings-swiped') });
        await checkLayout(page, 'Abstain Challenge');
    });

    expect(errors).toEqual([]);
});
