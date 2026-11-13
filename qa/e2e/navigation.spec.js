// Moving through the app: all five tabs work for someone with no GoalLeague, Back returns to where you came from
// (with the scroll position) or to a sensible parent after a direct link, sheets close on Back without leaving the
// page, and tapping the tab you're on goes back to its top. All without a full page reload, and the bars never move.
import { expect, test } from '@playwright/test';

const TABS = ['GoalLeagues', 'Matchups', 'Highlights', 'My Locker', 'Clubhouse'];

const mark = (page) => page.evaluate(() => {
    window.__navMarker = 'still here';
});
const stillHere = (page) => page.evaluate(() => window.__navMarker);
const bar = (page, testInfo) => page.locator(testInfo.project.use.isMobile ? '#app-tabbar' : '#app-topbar');

function watchErrors(page) {
    const errors = [];
    page.on('console', (message) => {
        // WebKit logs a fetch it cancelled because the page moved on (a prefetch, say) as "Load failed": the same as
        // Chromium's ERR_ABORTED, which isn't logged. Not an error in the app.
        if (message.type() === 'error' && !/Load failed$/.test(message.text())) {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        // Same story for Turbo's tab prefetch on touch, cancelled by the navigation it was warming up for.
        if (!/Load failed$/.test(error.message)) {
            errors.push(error.message);
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 500) {
            errors.push(`${response.status()} ${response.url()}`);
        }
    });

    return errors;
}

async function startDemo(page) {
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    await page.goto(page.url());
    await page.waitForLoadState('networkidle');
}

/**
 * A brand-new member with no goal and no GoalLeague, past the welcome walkthrough.
 */
async function newMember(page) {
    // Sign-up is rate limited (10 a minute); back-to-back QA runs can hit it, so wait out the minute and try once more.
    for (let attempt = 1; attempt <= 2; attempt++) {
        await page.goto('/register');
        await page.getByLabel('Name').fill('Nova Newcomer');
        await page.getByLabel('Email').fill(`nova.${Date.now()}.${Math.floor(Math.random() * 1e6)}@example.test`);
        await page.getByLabel('Password', { exact: true }).fill('a-long-password-123');
        await page.getByLabel('Confirm password').fill('a-long-password-123');
        const response = page.waitForResponse((each) => each.request().method() === 'POST' && each.url().endsWith('/register'));
        await page.getByRole('button', { name: /create account/i }).click();

        if ((await response).status() !== 429) {
            break;
        }

        await page.waitForTimeout(61_000);
    }
    await page.waitForLoadState('networkidle');

    // Finish the walkthrough the way its last step does.
    await page.goto('/welcome/rules');
    await page.locator('form[action$="/welcome/finish"] button[type="submit"]').first().click();
    await page.waitForURL('**/dashboard');
    await page.waitForLoadState('networkidle');
}

test('with no GoalLeague, all five tabs are tappable, swap in place, and the bar stays put', async ({ page }, testInfo) => {
    // Known issue (2026-11-11): Playwright's Windows WebKit build crashes ("Page crashed") on this brand-new member's
    // tab tour when page cross-fades (View Transitions) are on. It happens with or without this round's changes, the
    // demo member's identical tour passes in shell.spec.js, and it needs checking on a real iPhone. Until then this
    // test runs WebKit with reduced motion, which turns the cross-fade off; Chromium runs it as is.
    if (testInfo.project.name.includes('WebKit')) {
        await page.emulateMedia({ reducedMotion: 'reduce' });
    }

    const errors = watchErrors(page);
    await newMember(page);
    await mark(page);
    const tabs = bar(page, testInfo);
    const before = await tabs.boundingBox();

    for (const label of TABS) {
        const tab = tabs.locator('a[data-tab]', { hasText: label }).first();
        await expect(tab).toBeVisible();
        await expect(tab).toBeEnabled();
        await tab.click();
        await expect(tab).toHaveAttribute('aria-current', 'page');
        await page.waitForLoadState('networkidle');

        expect(await stillHere(page), `${label}: no full page reload`).toBe('still here');
        expect(await tabs.boundingBox(), `${label}: the bar didn't move`).toEqual(before);

        if (label === 'Matchups') {
            await expect(page.locator('[data-matchup-hub="no-league"]')).toBeVisible();
            await expect(page.getByText('No Matchup yet.', { exact: true })).toBeVisible();
        }
    }

    // History and the Matchups page load cleanly.
    await page.goto('/dashboard?tab=history');
    await expect(page.getByText('Your history starts here')).toBeVisible();
    expect(errors).toEqual([]);
});

test('Back returns to the page you came from, at the same scroll position, without a reload', async ({ page }, testInfo) => {
    await startDemo(page);
    await bar(page, testInfo).locator('a[data-tab]', { hasText: 'GoalLeagues' }).first().click();
    await page.waitForURL('**/leagues');
    await page.waitForLoadState('networkidle');
    await mark(page);

    const league = page.locator('a[href*="/leagues/"]').filter({ hasText: /Marathon|4\.0/ }).first();
    await league.scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollBy(0, 40));
    const y = await page.evaluate(() => window.scrollY);
    await league.click();
    await page.waitForURL(/\/leagues\/\d+$/);
    await page.waitForLoadState('networkidle');

    const back = page.locator('#app-topbar a[data-back]');
    await expect(back).toHaveAttribute('aria-label', 'Back to GoalLeagues');
    expect((await back.boundingBox()).height).toBeGreaterThanOrEqual(44);
    await back.click();
    await page.waitForURL('**/leagues');
    await page.waitForLoadState('networkidle');

    expect(await stillHere(page), 'no full page reload').toBe('still here');
    expect(Math.abs((await page.evaluate(() => window.scrollY)) - y), 'scroll position restored').toBeLessThan(60);
    await expect(page.locator('#app-topbar a[data-back]')).toHaveCount(0);
});

test('after a direct link, Back goes to the sensible parent', async ({ page }) => {
    await startDemo(page);
    await page.goto('/leagues');
    const href = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((h) => /\/leagues\/\d+$/.test(h)));

    // Arriving straight at the standings, as from a shared link, in a fresh tab with no history.
    const fresh = await page.context().newPage();
    await fresh.goto(`${href}/standings`);
    await fresh.waitForLoadState('networkidle');
    await fresh.locator('#app-topbar a[data-back]').click();
    await fresh.waitForURL(href);
    await expect(fresh.locator('#app-topbar a[data-back]')).toHaveAttribute('aria-label', 'Back to GoalLeagues');
});

test('a sheet closes on Back and the page stays', async ({ page }) => {
    await startDemo(page);
    await mark(page);
    const url = page.url();

    await page.locator('[data-checkin-open]').first().click();
    const sheet = page.locator('dialog[data-checkin-sheet]');
    await expect(sheet).toHaveAttribute('open', '');

    await page.goBack();
    await expect(sheet).not.toHaveAttribute('open', '');
    expect(page.url()).toBe(url);
    expect(await stillHere(page), 'no navigation, no reload').toBe('still here');

    // Closing it with its X leaves history as it was: Back now leaves the page as usual.
    await page.locator('[data-checkin-open]').first().click();
    await expect(sheet).toHaveAttribute('open', '');
    await sheet.locator('button[aria-label="Close"]').click();
    await expect(sheet).not.toHaveAttribute('open', '');
    expect(page.url()).toBe(url);
});

test('tapping the tab you are on scrolls to the top, then resets to its top level', async ({ page }, testInfo) => {
    await startDemo(page);
    await page.locator('[data-segmented], [role="tablist"][aria-label="Clubhouse view"]').getByRole('tab', { name: 'History' }).click();
    await page.waitForURL('**/dashboard?tab=history');
    await page.waitForLoadState('networkidle');
    await page.locator('html[data-page-ready]').waitFor({ state: 'attached' });
    await page.waitForTimeout(400); // Let the page transition finish, so its scroll reset doesn't undo ours.
    await page.evaluate(() => window.scrollTo(0, 500));
    const scrolled = (await page.evaluate(() => window.scrollY)) > 4;

    const clubhouse = bar(page, testInfo).locator('a[data-tab]', { hasText: 'Clubhouse' }).first();

    // Scrolled down: the first tap only scrolls to the top. (A page too short to scroll skips straight to the reset.)
    if (scrolled) {
        await clubhouse.click();
        await expect.poll(() => page.evaluate(() => window.scrollY)).toBeLessThan(5);
        expect(page.url()).toContain('tab=history');
    }

    await clubhouse.click();
    await page.waitForURL(/\/dashboard$/);
    await expect(page.locator('[role="tablist"][aria-label="Clubhouse view"] [aria-selected="true"]')).toHaveText(/Now/);
});
