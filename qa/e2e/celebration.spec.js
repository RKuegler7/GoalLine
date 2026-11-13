// Phase P: the live band and the band-up celebration. Checking in updates the band track in place (no reload), and
// the celebration plays, can be skipped with a tap, offers "Add proof" for an unproven Smashed week, and only fades
// with reduced motion.
import { expect, test } from '@playwright/test';

async function startDemo(page) {
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
    await page.goto(page.url());
    await page.waitForFunction(() => document.documentElement.dataset.pageReady === '1');
    await page.evaluate(() => {
        window.__noReload = true;
    });
}

test('checking in updates the live band in place, with no reload', async ({ page }) => {
    await startDemo(page);
    await page.goto('/today');
    await page.waitForFunction(() => document.documentElement.dataset.pageReady === '1');
    // From here on, the check-in must not reload the page.
    await page.evaluate(() => {
        window.__noReload = true;
    });

    const track = page.locator('[data-region="today-bands"] [data-band-track]').first();
    await expect(track).toBeVisible();
    const before = Number(await track.getAttribute('data-percent'));

    const open = page.locator('[data-region="gameplan"] form[data-optimistic="check"] button[data-check="open"]').first();
    await open.click();
    await expect(page.locator('.gl-toasts')).toContainText(/Checked in|on the line/i);

    // The server's new values are on the track (same or higher: today's task may belong to another goal).
    const after = Number(await page.locator('[data-region="today-bands"] [data-band-track]').first().getAttribute('data-percent'));
    expect(after).toBeGreaterThanOrEqual(before);
    expect(await page.evaluate(() => window.__noReload)).toBe(true);
});

test('the celebration plays, nudges for proof, and a tap skips it', async ({ page }) => {
    await startDemo(page);

    await page.evaluate(() => document.dispatchEvent(new CustomEvent('goalline:band-up', {
        detail: { band: 'smashed', percent: '152', needsProof: true, goalUrl: '/dashboard#goalweek-6' },
    })));

    const overlay = page.locator('[data-band-celebration]');
    await expect(overlay).toBeVisible();
    await expect(overlay).toHaveAttribute('role', 'status');
    await expect(overlay).toContainText('Smashed it.');
    await expect(overlay.locator('[data-celebration-proof]')).toBeVisible();
    await expect(overlay.locator('[data-celebration-proof]')).toHaveText('Add proof to lock it in');

    await overlay.locator('[data-celebration-title]').click();
    await expect(overlay).toBeHidden();

    // In line, without a proof nudge, leaves on its own in under about 1.5 seconds.
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('goalline:band-up', { detail: { band: 'in_line' } })));
    await expect(overlay).toContainText("You're on the line.");
    await expect(overlay.locator('[data-celebration-proof]')).toBeHidden();
    await expect(overlay).toBeHidden({ timeout: 2_000 });
});

test('with reduced motion the celebration only fades', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await startDemo(page);

    await page.evaluate(() => document.dispatchEvent(new CustomEvent('goalline:band-up', { detail: { band: 'past' } })));
    const card = page.locator('[data-celebration-card]');
    await expect(card).toBeVisible();
    expect(await card.evaluate((element) => getComputedStyle(element).animationName)).toBe('gl-celebrate-fade');
});
