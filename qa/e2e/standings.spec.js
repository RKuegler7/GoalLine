// The Standings table in real browsers, from the demo: it's the default view; Rank, Player, Stars, Record and Line ★
// show without swiping at phone widths; the table swipes sideways inside its own box (the page never does) with the
// Player column staying put; a heading sorts it in place and "Reset to official order" puts it back; and a row opens
// the player's season in a sheet that Back closes.
import { expect, test } from '@playwright/test';

test.use({ reducedMotion: 'reduce' });

async function startDemo(page) {
    await page.goto('/demo', { timeout: 150_000 });
    await page.waitForURL('**/dashboard', { timeout: 150_000 });
    await page.evaluate(() => fetch('/tour', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin',
    }));
}

/**
 * The demo league with the most settled weeks: its standings have real numbers in every column.
 */
async function standingsUrl(page) {
    await page.goto('/leagues');
    const leagues = await page.evaluate(() => [...new Set([...document.querySelectorAll('a[href]')].map((a) => a.href).filter((href) => /\/leagues\/\d+$/.test(href)))]);

    for (const league of leagues) {
        await page.goto(`${league}/standings`);
        if (await page.locator('[data-form-week]').count()) {
            return `${league}/standings`;
        }
    }

    throw new Error('No demo league with final weeks');
}

test('Standings: one swipeable table', async ({ page }, testInfo) => {
    test.setTimeout(240_000);
    const errors = [];
    page.on('pageerror', (error) => {
        if (!/Load failed$/.test(error.message)) {
            errors.push(error.message);
        }
    });

    await startDemo(page);
    const url = await standingsUrl(page);
    const table = page.locator('[data-standings-table]');
    const scroller = table.locator('[data-table-scroll]');

    await test.step('Table is the default and the key columns fit without swiping', async () => {
        await expect(page.locator('[data-standings-switch] [aria-current]')).toHaveText(/Table/);
        for (const width of [375, 390, 430]) {
            await page.setViewportSize({ width, height: 800 });
            await page.goto(url);
            const box = await scroller.boundingBox();

            for (const col of ['total', 'record', 'line']) {
                const head = await table.locator(`th[data-col="${col}"]`).boundingBox();
                expect(head.x + head.width, `${col} visible at ${width}px`).toBeLessThanOrEqual(box.x + box.width + 1);
            }

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, `no page overflow at ${width}px`).toBeLessThanOrEqual(0);
        }

        await page.screenshot({ path: testInfo.outputPath('standings-default.png') });
        await page.screenshot({ path: `qa/screenshots/standings-${testInfo.project.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}-default.png` });
    });

    await test.step('Swipe for more: the table scrolls, the page does not, Player stays put', async () => {
        const playerBefore = await table.locator('tbody tr th').first().boundingBox();
        await scroller.evaluate((el) => el.scrollBy({ left: 400, behavior: 'instant' }));
        await expect.poll(() => scroller.evaluate((el) => el.scrollLeft)).toBeGreaterThan(100);
        expect(await page.evaluate(() => window.scrollX)).toBe(0);
        const playerAfter = await table.locator('tbody tr th').first().boundingBox();
        expect(Math.abs(playerAfter.x - playerBefore.x)).toBeLessThan(2);
        await expect(table.locator('th[data-col="plus_minus"]')).toBeAttached();
        await page.screenshot({ path: `qa/screenshots/standings-${testInfo.project.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}-swiped.png` });
        await scroller.evaluate((el) => el.scrollTo({ left: 0, behavior: 'instant' }));
    });

    await test.step('Sort by a column, then reset to the official order', async () => {
        const order = () => table.locator('tbody tr').evaluateAll((rows) => rows.map((row) => row.dataset.rank));
        const official = await order();

        await table.locator('[data-sort-col="line"]').click();
        await expect(table.locator('th[data-col="line"]')).toHaveAttribute('aria-sort', 'descending');
        await expect(table.locator('[data-sort-note]')).toContainText('Line ★: earned by your own plan each week.');
        await expect(page).toHaveURL(/col=line/);
        const lines = await table.locator('tbody [data-cell="line"]').evaluateAll((cells) => cells.map((cell) => Number(cell.dataset.value)));
        expect(lines).toEqual([...lines].sort((a, b) => b - a));

        await table.locator('[data-reset-order]').click();
        await expect(table.locator('[data-reset-order]')).toBeHidden();
        expect(await order()).toEqual(official);
        await expect(page).not.toHaveURL(/col=/);
    });

    await test.step('A row opens the player\'s season in a sheet; Back closes it', async () => {
        await table.locator('tbody tr').first().locator('[data-cell="record"]').click();
        const sheet = page.locator('dialog[data-task-sheet][open]');
        await expect(sheet.locator('#player-sheet-title')).toBeVisible();
        await expect(sheet.locator('#week-by-week')).toBeAttached();
        await page.screenshot({ path: `qa/screenshots/standings-${testInfo.project.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}-player-sheet.png` });
        await page.goBack();
        await expect(page.locator('dialog[data-task-sheet][open]')).toHaveCount(0);
        await expect(page).toHaveURL(/\/standings/);
    });

    expect(errors).toEqual([]);
});
