// Phase Q: "Share your week" from the weekly recap: the card preview, feed and story sizes, a real PNG download (drawn
// by the server, or by the browser when the server can't), and a public link that opens for someone signed out.
import { expect, test } from '@playwright/test';

test('share your week: preview, download a PNG, and open the public link', async ({ page, browser }) => {
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    await page.goto('/my-week');
    await page.waitForFunction(() => document.documentElement.dataset.pageReady === '1');

    await page.locator('[data-share-week]').first().click();
    await expect(page).toHaveURL(/\/goalweeks\/\d+\/share$/);
    await page.waitForFunction(() => document.documentElement.dataset.pageReady === '1');

    const feed = page.locator('[data-card-size="feed"] svg');
    await expect(feed).toBeVisible();
    await expect(feed).toHaveAttribute('height', '1350');

    // Story size.
    await page.getByRole('tab', { name: 'Story' }).click();
    await expect(page.locator('[data-card-size="story"] svg')).toBeVisible();
    await expect(page.locator('[data-card-size="story"] svg')).toHaveAttribute('height', '1920');

    // Download: a real PNG file.
    const [download] = await Promise.all([page.waitForEvent('download'), page.locator('[data-card-download]').click()]);
    expect(download.suggestedFilename()).toMatch(/story\.png$/);
    const path = await download.path();
    const { readFileSync } = await import('node:fs');
    const bytes = readFileSync(path);
    expect(bytes.subarray(1, 4).toString()).toBe('PNG');

    // Turn on the public link; it opens signed out, with link-preview tags.
    await page.getByRole('button', { name: 'Make a link' }).click();
    const link = await page.locator('#card-public-url').inputValue();
    expect(link).toMatch(/\/c\/[A-Za-z0-9]{48}$/);

    const stranger = await browser.newContext();
    const other = await stranger.newPage();
    await other.goto(new URL(link).pathname);
    await expect(other.locator('[data-public-card] svg')).toBeVisible();
    await expect(other.getByRole('link', { name: 'Join GoalLine' })).toBeVisible();
    expect(await other.locator('meta[property="og:title"]').getAttribute('content')).toContain('on GoalLine');
    await stranger.close();
});
