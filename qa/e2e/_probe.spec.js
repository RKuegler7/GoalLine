import { test } from '@playwright/test';
test('probe', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/demo'); await page.waitForURL('**/dashboard', { timeout: 90000 });
  for (const [name, url] of [['clubhouse', '/dashboard'], ['leagues', '/leagues']]) {
    await page.goto(url); await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `qa/after/probe-${name}.png` });
  }
});
