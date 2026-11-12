// No check-ins before kickoff, in real browsers: a new league with a future kickoff shows the countdown banner and
// locked "Upcoming" tasks (tapping one explains why), the plan can still be edited, the server refuses a check-in
// even when the UI is bypassed, a league that has kicked off (the demo) still checks in, and the Clubhouse offers both
// Start buttons. No console errors, the bottom bar stays fixed, and nothing scrolls sideways.
import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const PASSWORD = 'a-long-password-123';
const unique = (name) => `${name}.${Date.now()}.${Math.floor(Math.random() * 1e6)}@example.test`;

function watchErrors(page) {
    const errors = [];
    page.on('console', (message) => {
        if (message.type() === 'error' && !/Load failed$|403/.test(message.text())) {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        if (!/Load failed$/.test(error.message)) {
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

async function register(page, name, email) {
    for (let attempt = 1; attempt <= 2; attempt++) {
        await page.goto('/register');
        await page.getByLabel('Name').fill(name);
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password', { exact: true }).fill(PASSWORD);
        await page.getByLabel('Confirm password').fill(PASSWORD);
        const response = page.waitForResponse((each) => each.request().method() === 'POST' && each.url().endsWith('/register'));
        await page.getByRole('button', { name: /create account/i }).click();

        if ((await response).status() !== 429) {
            break;
        }

        await page.waitForTimeout(61_000);
    }
    await page.waitForLoadState('networkidle');

    const log = fs.readFileSync(path.resolve('storage/logs/laravel.log'), 'utf8').replace(/=\r?\n/g, '').replace(/=3D/g, '=');
    const entry = log.split(/\n(?=\[\d{4}-\d{2}-\d{2} )/).reverse().find((each) => each.includes(email) && /\/email\/verify\//.test(each));
    await page.goto(entry.match(/https?:\/\/127\.0\.0\.1:\d+\/email\/verify\/[^\s"'<>)\]]+/)[0]);
    await page.goto('/welcome/rules');
    await page.locator('form[action$="/welcome/finish"] button[type="submit"]').first().click();
    await page.waitForURL('**/dashboard');
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
    const nav = page.locator('#app-tabbar');

    if (await nav.count() && await nav.isVisible()) {
        expect(await nav.evaluate((element) => getComputedStyle(element).position)).toBe('fixed');
    }
}

test('a league before kickoff locks check-ins and keeps the plan editable', async ({ page }, testInfo) => {
    test.setTimeout(6 * 60 * 1000);
    const errors = watchErrors(page);
    await register(page, 'Kai Kickoff', unique('kai'));

    // The Clubhouse offers a GoalLeague and a challenge side by side.
    await page.goto('/dashboard');
    await expect(page.locator('[data-start-choices] [data-start="challenge"]').first()).toBeVisible();
    for (const start of await page.locator('[data-start-choices] a').all()) {
        if (await start.isVisible()) {
            expect((await start.boundingBox()).height).toBeGreaterThanOrEqual(44);
        }
    }

    // A league kicking off on the second Monday from now (never today), with a new goal.
    await page.goto('/leagues/create');
    await expect(page.getByText('Week 1 starts Monday at 12:00 a.m. Eastern.')).toBeVisible();
    await page.getByLabel('GoalLeague name').fill('Kickoff Crew');
    await page.locator('#category').selectOption('fitness');
    await page.locator('#starts_on').selectOption({ index: 1 });
    await page.locator('#goal_id').selectOption('new');
    await page.locator('#goal_title').fill('Run a half marathon');
    const target = new Date(Date.now() + 90 * 86400000).toISOString().slice(0, 10);
    await page.locator('#goal_target_date').fill(target);
    await page.locator('#ends_on').fill(target);
    await expect(page.locator('[data-season-length]')).toContainText('Season:');
    await page.locator('form[action$="/leagues"] button[type="submit"]').last().click();
    await page.waitForURL(/\/goals\/\d+\/intake/);
    const goalUrl = page.url().replace(/\/intake.*$/, '');
    await page.waitForLoadState('networkidle');
    await page.locator('button[name="skip"]:visible').first().click();
    await page.waitForLoadState('networkidle');

    // The goal page: banner, countdown, locked tasks.
    await page.goto(goalUrl);
    const accept = page.getByRole('button', { name: 'Accept this plan' });
    if (await accept.count()) {
        await accept.click();
        await page.waitForLoadState('networkidle');
    }
    const banner = page.locator('[data-kickoff-banner]');
    await expect(banner).toBeVisible();
    await expect(banner).toContainText('Your season kicks off');
    await expect(banner.locator('[data-countdown]')).toContainText(/to go/);
    await expect(page.locator('form[data-optimistic="check"] button[data-check="open"]')).toHaveCount(0);
    const locked = page.locator('button[data-check="locked"]:visible').first();
    await expect(locked).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('goal-before-kickoff.png'), fullPage: false });
    await locked.click();
    await expect(page.locator('dialog#kickoff-sheet[open]')).toBeVisible();
    await expect(page.locator('dialog#kickoff-sheet')).toContainText('Get your plan just right before kickoff.');
    await page.screenshot({ path: testInfo.outputPath('kickoff-sheet.png') });
    await page.locator('dialog#kickoff-sheet button[aria-label="Close"]').click();

    // The server refuses a check-in even when the UI is skipped.
    const taskId = await page.evaluate(() => document.querySelector('form[action*="/roadmap-tasks/"]')?.action.match(/roadmap-tasks\/(\d+)/)?.[1]);
    if (taskId) {
        const status = await page.evaluate((id) => fetch(`/roadmap-tasks/${id}/check`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'intent=done',
            credentials: 'same-origin',
        }).then((response) => response.status), taskId);
        expect(status).toBe(403);
    }

    // The Clubhouse and Today show the same kickoff state.
    await page.goto('/dashboard');
    await expect(page.locator('[data-kickoff-banner]')).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('clubhouse-before-kickoff.png') });
    await bottomBarFixed(page);
    await noSidewaysScroll(page);
    await page.goto('/today');
    await expect(page.locator('[data-kickoff-banner]')).toBeVisible();
    await noSidewaysScroll(page);

    expect(errors).toEqual([]);
});

test('a league that has kicked off still checks in', async ({ page }) => {
    const errors = watchErrors(page);
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.evaluate(() => fetch('/tour', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin',
    }));
    const goalUrl = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((href) => /\/goals\/\d+$/.test(href)));
    await page.goto(goalUrl);
    await expect(page.locator('[data-kickoff-banner]')).toHaveCount(0);
    const open = page.locator('form[data-optimistic="check"] button[data-check="open"]:visible').first();
    await open.scrollIntoViewIfNeeded();
    const key = await open.evaluate((button) => button.closest('form').dataset.liveKey);
    await open.click();
    await expect(page.locator(`form[data-live-key="${key}"] [data-check="done"]`)).toBeVisible();
    await noSidewaysScroll(page);
    expect(errors).toEqual([]);
});
