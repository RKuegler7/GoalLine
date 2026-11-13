// Explore in real browsers: from Highlights, switch to Explore, see the public example GoalLeagues and public posts,
// tap Join (the normal join flow), and check a post's "Who sees it" never starts on Public. No console errors, no
// sideways scroll, the bottom bar stays fixed.
import { expect, test } from '@playwright/test';

test.use({ reducedMotion: 'reduce' });

function watchErrors(page) {
    const errors = [];
    const noise = /Load failed$|127\.0\.0\.1:\d+\/\S* due to access control checks\.$/;
    page.on('console', (message) => {
        if (message.type() === 'error' && !noise.test(message.text())) {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        if (!noise.test(error.message)) {
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

test('Explore: public GoalLeagues, public posts, and Join', async ({ page }, testInfo) => {
    test.setTimeout(4 * 60 * 1000);
    const errors = watchErrors(page);
    await page.goto('/demo', { timeout: 90_000 });
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin',
    }));

    await page.goto('/highlights');
    await page.locator('[data-highlights-switch] a', { hasText: 'Explore' }).click();
    await page.waitForURL(/\/highlights\/explore/);

    await expect(page.locator('[data-explore-examples]')).toBeVisible();
    await expect(page.locator('[data-public-league]').first()).toBeVisible();
    await expect(page.locator('[data-public-post]').first()).toBeVisible();
    await expect(page.locator('[data-inspiration] a').first()).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('explore.png') });

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);
    const bar = page.locator('#app-tabbar');
    if (await bar.isVisible()) {
        expect(await bar.evaluate((element) => getComputedStyle(element).position)).toBe('fixed');
    }

    // Join goes through the usual invite page.
    await page.locator('[data-public-league] [data-join-public]').first().click();
    await page.waitForURL(/\/(join|i|invite|leagues)\//);
    await expect(page.locator('body')).toContainText(/join|goal/i);

    // My own post's "Who sees it" never starts on Public.
    await page.goto('/highlights');
    const select = page.locator('[data-post-visibility] select').first();
    if (await select.count()) {
        expect(await select.inputValue()).not.toBe('public');
    }

    expect(errors).toEqual([]);
});
