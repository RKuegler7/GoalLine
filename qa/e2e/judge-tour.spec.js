// The Judge's tour in demo mode: the pill opens a sheet of six items, "Take me there" goes to the right place, the
// item ticks itself off, the pill counts it, and the sheet works from the keyboard.
import { expect, test } from '@playwright/test';

test.use({ reducedMotion: 'reduce' });

test('Judge\'s tour: open, take me there, auto-check', async ({ page }, testInfo) => {
    test.setTimeout(240_000);
    await page.goto('/demo', { timeout: 150_000 });
    await page.waitForURL('**/dashboard', { timeout: 150_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    await page.goto('/dashboard');

    const pill = page.locator('[data-judge-tour-open]');
    await expect(pill).toContainText('0 of 6');

    // Keyboard: focus the pill, Enter opens the sheet, Escape closes it and focus returns.
    await pill.focus();
    await page.keyboard.press('Enter');
    const sheet = page.locator('dialog[data-judge-tour-sheet][open]');
    await expect(sheet.locator('[data-judge-tour-item]')).toHaveCount(6);
    await page.screenshot({ path: `qa/screenshots/judge-tour-${testInfo.project.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}.png` });
    await page.keyboard.press('Escape');
    await expect(page.locator('dialog[data-judge-tour-sheet][open]')).toHaveCount(0);
    await expect(pill).toBeFocused();
    await page.waitForLoadState('networkidle');

    // Take me there: the Standings table, which ticks the item off.
    await pill.click();
    await sheet.locator('[data-judge-tour-item="standings"] a').click();
    await page.waitForURL(/\/standings$/);
    await expect(page.locator('[data-standings-table]')).toBeVisible();
    await page.goto('/dashboard');
    await expect(page.locator('[data-judge-tour-open]')).toContainText('1 of 6');

    // The AI panel opens on arrival and ticks itself off.
    await page.locator('[data-judge-tour-open]').click();
    await page.locator('dialog[data-judge-tour-sheet][open] [data-judge-tour-item="ai_plan"] a').click();
    await expect(page.locator('[data-judge-tour-ping] [id="ai-code-split-title"]')).toBeVisible();
    await expect(page.locator('details[data-judge-tour-ping]')).toHaveAttribute('open', '');
    await page.waitForLoadState('networkidle');
    await page.goto('/dashboard');
    await expect(page.locator('[data-judge-tour-open]')).toContainText('2 of 6');

    // No sideways scroll with the pill on screen.
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
});
