// The season finale in real browsers: from the demo, "Preview season finale" opens the finished CPA season; every
// card renders (screenshotted one by one at phone size), the share card falls back to Download where the phone's
// share sheet isn't available, a recommended next goal opens the goal form filled in, and that form leads on to the
// questions. No console errors, no sideways scroll at 375, 390 and 430 px, the bottom bar stays fixed, reduced
// motion is respected, and text meets WCAG AA contrast.
import { expect, test } from '@playwright/test';

test.use({ reducedMotion: 'reduce' });

function watchErrors(page) {
    const errors = [];
    // Windows WebKit reports cancelled same-site fetches as "Load failed" or "… due to access control checks".
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
    await page.goto('/demo', { timeout: 90_000 });
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin',
    }));
}

/**
 * WCAG AA text contrast for the visible text inside a card: 4.5:1, or 3:1 for large text (24px, or 18.66px bold).
 */
async function lowContrast(locator) {
    return locator.evaluate((root) => {
        const parse = (color) => (color.match(/[\d.]+/g) || []).map(Number);
        const luminance = ([r, g, b]) => {
            const channel = (value) => {
                const c = value / 255;

                return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
            };

            return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
        };
        const background = (element) => {
            for (let node = element; node; node = node.parentElement) {
                const style = getComputedStyle(node);
                const color = parse(style.backgroundColor);

                if (style.backgroundImage !== 'none') {
                    return null; // Text over an image or gradient: not checkable this way.
                }

                if (color.length >= 3 && (color[3] === undefined || color[3] > 0.95)) {
                    return color;
                }
            }

            return [250, 250, 245];
        };
        const problems = [];

        for (const element of root.querySelectorAll('h1, h2, h3, p, span, a, dt, dd, li, button')) {
            const own = [...element.childNodes].some((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());

            if (!own || !element.getClientRects().length) {
                continue;
            }

            const style = getComputedStyle(element);
            const back = background(element);
            const fore = parse(style.color);

            if (!back || fore.length < 3) {
                continue;
            }

            // Blend semi-transparent text over its background.
            const alpha = fore[3] ?? 1;
            const mixed = fore.slice(0, 3).map((value, index) => value * alpha + back[index] * (1 - alpha));
            const [light, dark] = [luminance(mixed), luminance(back)].sort((a, b) => b - a);
            const ratio = (light + 0.05) / (dark + 0.05);
            const size = parseFloat(style.fontSize);
            const large = size >= 24 || (size >= 18.66 && Number(style.fontWeight) >= 700);

            if (ratio < (large ? 3 : 4.5)) {
                problems.push(`${element.textContent.trim().slice(0, 40)} (${ratio.toFixed(2)}:1)`);
            }
        }

        return problems;
    });
}

test('the season finale preview: every card, share, and a next goal', async ({ page }, testInfo) => {
    test.setTimeout(5 * 60 * 1000);
    const errors = watchErrors(page);
    await startDemo(page);

    // From History.
    await page.goto('/dashboard?tab=history');
    await page.locator('[data-preview-finale]').click();
    await page.waitForURL(/\/goals\/\d+\/finale/);
    await expect(page.locator('[data-finale-preview-note], [data-finale]').first()).toBeVisible();

    // The demo season has highlights, so the reel shows.
    await expect(page.locator('[data-finale-card="reel"]')).toHaveCount(1);

    // Card screenshots without the app's fixed bars on top of them (only for the pictures).
    const bars = await page.addStyleTag({ content: '#app-tabbar, [data-checkin-open], header { visibility: hidden !important; }' });

    for (const card of ['result', 'two', 'replay', 'reel', 'news', 'medals', 'share', 'next']) {
        const section = page.locator(`[data-finale-card="${card}"]`);

        if (!(await section.count())) {
            continue;
        }

        await section.scrollIntoViewIfNeeded();
        await expect(section).toBeVisible();
        await section.screenshot({ path: testInfo.outputPath(`finale-${card}.png`) });
        expect(await lowContrast(section), `${card}: text contrast`).toEqual([]);
    }

    await bars.evaluate((style) => style.remove());

    // Share: Download always works; the Share button only appears where the phone can share a file.
    const share = page.locator('[data-finale-card="share"]');
    if (await share.count()) {
        await expect(share.locator('[data-card-download]')).toBeVisible();
    }

    // A recommended next goal opens the goal form filled in, and the form leads on to the questions.
    const idea = page.locator('[data-recommendation]').first();
    const title = (await idea.locator('span.block').first().innerText()).trim();
    await idea.click();
    await page.waitForURL(/\/goals\/create/);
    await expect(page.locator('#title')).toHaveValue(title);
    await page.locator('form[action$="/goals"] button[type="submit"]').last().click();
    await page.waitForURL(/\/goals\/\d+\/intake/);

    expect(errors).toEqual([]);
});

for (const width of [375, 390, 430]) {
    test(`the finale fits a ${width}px phone`, async ({ page }, testInfo) => {
        test.skip(testInfo.project.name.startsWith('Desktop'), 'phone widths only');
        await page.setViewportSize({ width, height: 844 });
        await startDemo(page);
        await page.goto('/finale/preview');
        await page.waitForURL(/\/goals\/\d+\/finale/);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);

        const bar = page.locator('#app-tabbar');
        if (await bar.isVisible()) {
            expect(await bar.evaluate((element) => getComputedStyle(element).position)).toBe('fixed');
        }

        // Reduced motion: the gold burst doesn't animate.
        const burst = page.locator('.gl-finale-burst');
        if (await burst.count()) {
            expect(await burst.evaluate((element) => getComputedStyle(element).animationName)).toBe('none');
        }
    });
}
