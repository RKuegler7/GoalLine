// Phase R: the branded loader. The splash shows on the first page of a visit only and leaves quickly; fast loads never
// flash a loader; slow ones show it; a failed load offers "Try again" that works.
import { expect, test } from '@playwright/test';

async function startDemo(page) {
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.waitForFunction(() => document.documentElement.dataset.pageReady === '1');
}

// Network failures are simulated with page.route, which a service worker would bypass.
test.use({ serviceWorkers: 'block' });

const opacityOf = (locator) => locator.evaluate((element) => Number(getComputedStyle(element).opacity));

test('the splash shows once per visit and never holds the page past about 1.2 seconds', async ({ page }) => {
    await page.goto('/login');
    // Gone quickly (the page is ready long before the 1.2 second cap), and the page is usable.
    await expect(page.locator('[data-splash]')).toHaveCount(0, { timeout: 2_000 });
    await page.waitForFunction(() => document.documentElement.dataset.pageReady === '1');

    // Not again on the next page of the same visit.
    await page.goto('/about');
    expect(await page.content()).not.toContain('data-splash');
});

test('with reduced motion the splash still leaves on its own', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/login');
    await expect(page.locator('[data-splash]')).toHaveCount(0, { timeout: 2_000 });
});

test('a fast load never flashes the loader; a slow one shows it', async ({ page }) => {
    await startDemo(page);
    const sheet = page.locator('[data-checkin-sheet]');
    const loader = sheet.locator('[data-checkin-body] .gl-loader');

    // Slow: held for 1.5 seconds. Under 250 ms the loader is still invisible; after that it shows.
    let release;
    await page.route('**/check-in', async (route) => {
        await new Promise((resolve) => {
            release = resolve;
        });
        await route.continue();
    });
    await page.locator('[data-checkin-open]').click();
    await expect(sheet).toBeVisible();
    expect(await opacityOf(loader)).toBeLessThan(0.1);
    await page.waitForTimeout(600);
    expect(await opacityOf(loader)).toBeGreaterThan(0.9);
    release();
    await expect(sheet.locator('[data-region="checkin-sheet"]')).toBeVisible();
    await expect(loader).toHaveCount(0);
    await page.unroute('**/check-in');
    await sheet.locator('button[aria-label="Close"]').click();

    // Fast: the list replaces the loader before it ever becomes visible.
    await page.locator('[data-checkin-open]').click();
    await expect(sheet.locator('[data-region="checkin-sheet"]')).toBeVisible();
    await expect(loader).toHaveCount(0);
});

test('a failed load shows a friendly error, and Try again works', async ({ page }) => {
    await startDemo(page);
    const sheet = page.locator('[data-checkin-sheet]');

    await page.route('**/check-in', (route) => route.abort('internetdisconnected'));
    await page.locator('[data-checkin-open]').click();
    const error = sheet.locator('.gl-load-error');
    await expect(error).toBeVisible();
    await expect(error).toContainText('didn’t load');
    await expect(error).not.toContainText(/exception|error code|stack/i);

    await page.unroute('**/check-in');
    await error.locator('[data-load-retry]').click();
    await expect(sheet.locator('[data-region="checkin-sheet"]')).toBeVisible();
});

test('a page that can’t be reached shows Try again instead of the browser’s offline page', async ({ page }) => {
    await startDemo(page);
    await page.evaluate(() => {
        window.__noReload = true;
    });

    await page.route('**/highlights', (route) => route.abort('internetdisconnected'));
    await page.locator('a[data-tab]:visible', { hasText: 'Highlights' }).first().click();
    const error = page.locator('[data-nav-error]');
    await expect(error).toBeVisible();
    expect(await page.evaluate(() => window.__noReload)).toBe(true);

    await page.unroute('**/highlights');
    await error.locator('[data-load-retry]').click();
    await expect(page).toHaveURL(/\/highlights$/);
    await expect(error).toBeHidden();
});
