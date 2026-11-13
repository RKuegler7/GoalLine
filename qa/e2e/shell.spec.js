// The app shell: tab switches swap only the page (no full reload), the bars never move, main actions keep the scroll
// position, and the matchup page scrolls vertically from anywhere, including over the swipeable pager.
import { expect, test } from '@playwright/test';

const TABS = ['GoalLeagues', 'Matchups', 'Highlights', 'My Locker', 'Clubhouse'];

async function startDemo(page) {
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    await page.waitForLoadState('networkidle');
    // Survives only if the page is never fully reloaded.
    await page.evaluate(() => {
        window.__shellMarker = 'still here';
    });
}

const stillHere = (page) => page.evaluate(() => window.__shellMarker);

test('switching tabs swaps the page in place and the bars never move', async ({ page }, testInfo) => {
    await startDemo(page);
    const phone = Boolean(testInfo.project.use.isMobile);
    const bar = page.locator(phone ? '#app-tabbar' : '#app-topbar');
    const before = await bar.boundingBox();

    for (const label of TABS) {
        const tab = bar.locator('a[data-tab]', { hasText: label }).first();
        await tab.click();
        await expect(tab).toHaveAttribute('aria-current', 'page');
        await page.waitForLoadState('networkidle');

        expect(await stillHere(page), `${label}: no full page reload`).toBe('still here');
        expect(await bar.boundingBox(), `${label}: the bar didn't move or resize`).toEqual(before);
    }

    // Back and forward work, still without a reload.
    await page.goBack();
    await page.waitForLoadState('networkidle');
    expect(await stillHere(page)).toBe('still here');
});

test('main actions keep the scroll position and never reload', async ({ page }) => {
    await startDemo(page);

    // Check off a task on the Clubhouse.
    const check = page.locator('[data-region="gameplan"] form[data-optimistic="check"] button[data-check="open"]').first();
    await check.scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollBy(0, 60));
    const y = await page.evaluate(() => window.scrollY);
    await check.click();
    await expect(page.locator('.gl-toasts')).toContainText(/Checked in|on the line|Day won/i);
    expect(Math.abs((await page.evaluate(() => window.scrollY)) - y)).toBeLessThan(40);
    expect(await stillHere(page)).toBe('still here');

    // Cheer a post on Highlights.
    await page.locator('a[data-tab]:visible').filter({ hasText: 'Highlights' }).first().click();
    await page.waitForLoadState('networkidle');
    const cheer = page.locator('form[action*="/cheer"] button[type="submit"]:visible').first();

    if (await cheer.count()) {
        await cheer.scrollIntoViewIfNeeded();
        const y2 = await page.evaluate(() => window.scrollY);
        await cheer.click();
        await page.waitForLoadState('networkidle');
        expect(Math.abs((await page.evaluate(() => window.scrollY)) - y2)).toBeLessThan(40);
    }

    expect(await stillHere(page)).toBe('still here');

    // Start a GoalLeague opens a sheet, not a new page.
    await page.locator('a[data-tab]:visible').filter({ hasText: 'GoalLeagues' }).first().click();
    await page.waitForLoadState('networkidle');
    await page.locator('[data-start-buttons] [data-open-sheet="start-sheet"]').click();
    await expect(page.locator('dialog#start-sheet[open]')).toBeVisible();
    await expect(page.locator('dialog#start-sheet')).toContainText('Start a challenge');
    expect(await stillHere(page)).toBe('still here');
});

test('the matchup page scrolls down from anywhere, including over the pager', async ({ page, browserName }, testInfo) => {
    await startDemo(page);
    await page.goto('/matchups');
    await page.waitForLoadState('networkidle');
    const track = page.locator('[data-pager-track]');

    // Nothing inside the pager scrolls vertically, and it lets vertical pans through to the page.
    const layout = await track.evaluate((element) => ({
        overflowY: getComputedStyle(element).overflowY,
        innerScroll: element.scrollHeight - element.clientHeight,
        touchAction: getComputedStyle(element).touchAction,
    }));
    expect(['clip', 'hidden']).toContain(layout.overflowY);
    expect(layout.innerScroll).toBeLessThanOrEqual(1);
    expect(layout.touchAction).toMatch(/pan-y|manipulation|auto/);

    // A real vertical swipe that starts on the pager scrolls the page (touch events need Chromium's protocol).
    if (browserName === 'chromium' && testInfo.project.use.hasTouch) {
        const box = await track.boundingBox();
        const x = box.x + box.width / 2;
        const startY = Math.min(box.y + 200, page.viewportSize().height - 120);
        const before = await page.evaluate(() => window.scrollY);
        const cdp = await page.context().newCDPSession(page);
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y: startY }] });

        for (let step = 1; step <= 8; step++) {
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x, y: startY - step * 30 }] });
        }

        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await page.waitForTimeout(400);
        expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(before + 100);
    }

    // The live numbers stay in view while scrolling: once the page scrolls past the scoreboard, the sticky strip sits
    // just under the top bar.
    await page.evaluate(() => {
        const sticky = document.querySelector('[data-pager-sticky]').getBoundingClientRect().top + window.scrollY;
        window.scrollTo(0, sticky + 300);
    });
    await page.waitForTimeout(200);
    const strip = await page.locator('[data-pager-sticky]').boundingBox();
    const topbar = await page.locator('#app-topbar').boundingBox();
    expect(Math.abs(strip.y - (topbar.y + topbar.height))).toBeLessThan(6);
});
