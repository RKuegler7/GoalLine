// The logged-out landing page on phones: the headline and both buttons are in the first screen, nothing scrolls
// sideways at 375, 390 and 430 px (the screens row scrolls inside itself), the images load, the layout doesn't shift
// as they arrive, and axe finds no serious or critical issues (contrast included).
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('Landing page: five-second first impression', async ({ page }, testInfo) => {
    const chromium = testInfo.project.name.includes('Chromium');

    if (chromium) {
        // Record layout shifts from the very start.
        await page.addInitScript(() => {
            window.__cls = 0;
            new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (!entry.hadRecentInput) {
                        window.__cls += entry.value;
                    }
                }
            }).observe({ type: 'layout-shift', buffered: true });
        });
    }

    for (const width of [375, 390, 430]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        // Above the fold: headline, Try the demo and Log in.
        for (const target of [page.getByRole('heading', { level: 1 }), page.locator('[data-try-demo]'), page.locator('[data-landing-hero] a', { hasText: 'Log in' })]) {
            const box = await target.boundingBox();
            expect(box.y + box.height, `in the first screen at ${width}px`).toBeLessThanOrEqual(800);
        }

        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), `no overflow at ${width}px`).toBeLessThanOrEqual(0);
    }

    await page.locator('[data-landing-screens]').scrollIntoViewIfNeeded();
    await page.waitForLoadState('networkidle');
    const loaded = await page.locator('[data-landing-screens] img').evaluateAll((images) => images.map((image) => image.complete && image.naturalWidth > 0));
    expect(loaded).toEqual([true, true, true]);

    if (chromium) {
        expect(await page.evaluate(() => window.__cls), 'cumulative layout shift').toBeLessThan(0.1);
    }

    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact));
    expect(serious.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).slice(0, 3).join(' | ')}`)).toEqual([]);

    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: `qa/screenshots/landing-${testInfo.project.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}.png`, fullPage: true });
});
