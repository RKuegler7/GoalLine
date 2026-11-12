// The profile and start buttons in real browsers: from the demo, open a league mate's profile from the standings, see
// the full name, the counts row and Add friend, scroll the sections, open Friends in place; edit my own bio; the
// start buttons stay on one line at 375, 390 and 430 px; Try the demo resumes the same demo; no console errors, no
// sideways scroll, and the bottom bar stays fixed.
import { expect, test } from '@playwright/test';

// Playwright's Windows WebKit build can crash on page view transitions (see navigation.spec.js); reduced motion
// turns them off. Real iPhones aren't affected.
test.use({ reducedMotion: 'reduce' });

function watchErrors(page) {
    const errors = [];
    // Windows WebKit reports cancelled same-site fetches (a prefetch cut short by navigating) as "Load failed" or
    // "… due to access control checks." Neither is an app error.
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

async function startDemo(page) {
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin',
    }));
}

async function noSidewaysScroll(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);
}

async function bottomBarFixed(page) {
    const bar = page.locator('#app-tabbar');

    if (await bar.isVisible()) {
        expect(await bar.evaluate((element) => getComputedStyle(element).position)).toBe('fixed');
    }
}

test('a league mate profile from the standings, then my own', async ({ page }, testInfo) => {
    test.setTimeout(5 * 60 * 1000);
    const errors = watchErrors(page);
    await startDemo(page);

    // Try the demo again from the same browser: the same demo comes back.
    const me = await page.evaluate(() => document.querySelector('a[href*="/u/"]')?.href);
    await page.goto('/demo');
    await page.waitForURL('**/dashboard');
    expect(await page.evaluate(() => document.querySelector('a[href*="/u/"]')?.href)).toBe(me);

    // Standings, then a league mate's season, then their profile.
    await page.goto('/leagues');
    const league = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((href) => /\/leagues\/\d+$/.test(href)));
    await page.goto(`${league}/standings`);
    // Someone else's row (my own is highlighted in mint).
    await page.locator('[data-standings-row]:not(.bg-mint) a').first().click();
    await page.waitForLoadState('networkidle');
    await page.locator('[data-player-season] a[href*="/u/"]').first().click();
    await page.waitForURL(/\/u\//);

    // The whole name is visible (not cut off), with the counts row and Add friend.
    const name = page.locator('[data-profile-name]');
    await expect(name).toBeVisible();
    expect(await name.evaluate((element) => element.scrollWidth <= element.clientWidth + 1 && element.scrollHeight <= element.clientHeight + 1)).toBe(true);
    await expect(page.locator('[data-profile-counts] [data-count]')).toHaveCount(4);
    await expect(page.locator('[data-profile-actions]')).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('profile-header.png') });

    // Sections by chip, and Friends in place.
    for (const id of ['goals', 'highlights', 'stats', 'trophy-case', 'history']) {
        await page.locator(`[data-profile-chips] a[href="#${id}"]`).click();
        await expect(page.locator(`#${id}`)).toBeInViewport();
    }
    // Let the last chip's in-page navigation settle before opening Friends in place.
    await page.waitForLoadState('networkidle');
    await page.locator('[data-open-friends]').click();
    await expect(page.locator('[data-friends-expand][open] [data-friends-page]')).toBeVisible();
    await bottomBarFixed(page);
    await noSidewaysScroll(page);

    // My own profile: change the bio.
    await page.goto('/profile/edit');
    const bio = `Testing the profile ${Date.now() % 10000}`;
    await page.getByLabel('Bio (optional)').fill(bio);
    await page.locator('form[action$="/profile"] button[type="submit"]').last().click();
    await page.waitForURL(/\/profile$/);
    await expect(page.getByText(bio)).toBeVisible();
    await noSidewaysScroll(page);

    expect(errors).toEqual([]);
});

for (const width of [375, 390, 430]) {
    test(`start buttons stay on one line at ${width}px`, async ({ page }, testInfo) => {
        test.skip(testInfo.project.name.startsWith('Desktop'), 'phone widths only');
        await page.setViewportSize({ width, height: 844 });
        await startDemo(page);

        for (const path of ['/leagues', '/leagues?tab=challenges', '/dashboard']) {
            await page.goto(path);
            await page.screenshot({ path: testInfo.outputPath(`start${path.replace(/\W+/g, '-')}-${width}.png`) });
            const buttons = page.locator('[data-start-choices] a:visible');

            for (const button of await buttons.all()) {
                // Lines of text: the distinct rows the label's visible characters sit on (icons don't count).
                const lines = await button.evaluate((element) => {
                    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
                    const tops = new Set();

                    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
                        if (!node.textContent.trim() || getComputedStyle(node.parentElement).display === 'none') {
                            continue;
                        }

                        const range = document.createRange();
                        range.selectNodeContents(node);
                        [...range.getClientRects()].filter((rect) => rect.width > 1).forEach((rect) => tops.add(Math.round(rect.top / 4)));
                    }

                    return tops.size;
                });
                expect(lines, `${path}: "${(await button.innerText()).trim()}" is one line`).toBeLessThanOrEqual(1);
                expect((await button.boundingBox()).height).toBeGreaterThanOrEqual(44);
            }

            await noSidewaysScroll(page);
        }

        await page.goto('/leagues');
        await page.screenshot({ path: testInfo.outputPath(`start-buttons-${width}.png`) });
    });
}
