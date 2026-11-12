// The invite and join flow, the scoring sheet, Help and the tour, in real browsers: an invite opened signed out
// through sign-up and email verification to the league goal, its questions, the plan and the Clubhouse; the scoring
// sheet closing four ways; Help opening at the right answer on three screens; the first-run tour; no overflow.
import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const PASSWORD = 'a-long-password-123';

function watchErrors(page) {
    const errors = [];
    page.on('console', (message) => {
        if (message.type() === 'error' && !/Load failed$/.test(message.text())) {
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

const unique = (name) => `${name}.${Date.now()}.${Math.floor(Math.random() * 1e6)}@example.test`;

/**
 * Fill and send the sign-up form that's on screen. Sign-up is rate limited (10 a minute), so back-to-back QA runs wait
 * out the minute and try once more.
 */
async function submitSignUp(page, name, email, reload) {
    for (let attempt = 1; attempt <= 2; attempt++) {
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
        await reload();
    }
    await page.waitForLoadState('networkidle');
}

async function register(page, name, email) {
    await page.goto('/register');
    await submitSignUp(page, name, email, () => page.goto('/register'));
}

async function verify(page, email) {
    await page.goto(verificationLink(email));
    await page.waitForLoadState('networkidle');
}

/**
 * Mark the first-run tour as seen, so it doesn't sit over the controls a test is about to use.
 */
async function tourSeen(page) {
    await page.evaluate(() => fetch('/tour', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin',
    }));
}

async function finishWelcome(page) {
    await page.goto('/welcome/rules');
    await page.locator('form[action$="/welcome/finish"] button[type="submit"]').first().click();
    await page.waitForURL('**/dashboard');
}

/**
 * The newest email verification link sent to this address, from the QA server's mail log (quoted-printable).
 */
function verificationLink(email) {
    const log = fs.readFileSync(path.resolve('storage/logs/laravel.log'), 'utf8').replace(/=\r?\n/g, '').replace(/=3D/g, '=');
    // Each log entry starts with "[2026-…". The newest one that is an email to this address and holds a link wins.
    const entry = log.split(/\n(?=\[\d{4}-\d{2}-\d{2} )/).reverse().find((each) => each.includes(email) && /\/email\/verify\//.test(each));
    const match = entry?.match(/https?:\/\/127\.0\.0\.1:\d+\/email\/verify\/[^\s"'<>)\]]+/);

    if (!match) {
        throw new Error(`No verification link logged for ${email}`);
    }

    return match[0];
}

async function skipTour(page) {
    const tour = page.locator('[data-tour]');
    if (await tour.isVisible().catch(() => false)) {
        await tour.locator('[data-tour-skip]').click();
    }
}

async function startDemo(page) {
    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await page.waitForLoadState('networkidle');
    await tourSeen(page);
    await page.reload();
    await page.waitForLoadState('networkidle');
}

async function demoLeague(page) {
    await page.goto('/leagues');
    return page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((h) => /\/leagues\/\d+$/.test(h)));
}

test('an invite opened signed out goes through sign-up and verification to the league goal, its plan and the Clubhouse', async ({ browser, baseURL }) => {
    test.setTimeout(8 * 60 * 1000);

    // The owner sets up a league with a goal.
    const ownerContext = await browser.newContext({ baseURL });
    const owner = await ownerContext.newPage();
    const ownerEmail = unique('corey');
    await register(owner, 'Corey Dojo', ownerEmail);
    await verify(owner, ownerEmail);
    await finishWelcome(owner);
    await tourSeen(owner);
    await owner.goto('/leagues/create');
    await owner.getByLabel('GoalLeague name').fill('Dojo Go Running');
    await owner.locator('#category').selectOption('fitness');
    await owner.locator('#goal_id').selectOption('new');
    await owner.locator('#goal_title').fill('Run a marathon');
    const target = new Date(Date.now() + 120 * 86400000).toISOString().slice(0, 10);
    await owner.locator('#goal_target_date').fill(target);
    await owner.locator('#ends_on').fill(target);
    await owner.locator('form[action$="/leagues"] button[type="submit"]').last().click();
    await owner.waitForURL(/\/goals\/\d+\/intake/);
    await owner.waitForLoadState('networkidle');
    await owner.locator('button[name="skip"]:visible').first().click();
    await owner.waitForURL(/\/leagues\/\d+/);
    const inviteUrl = await owner.locator('#invite-link').inputValue();
    await ownerContext.close();

    // A visitor opens it signed out, signs up and verifies.
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    const errors = watchErrors(page);
    const email = unique('nova');
    await page.goto(inviteUrl);
    await expect(page.getByText('Goal: Run a marathon by', { exact: false }).first()).toBeVisible();
    await expect(page.getByText('Corey invited you')).toBeVisible();
    await page.getByRole('link', { name: 'Create an account' }).click();
    await submitSignUp(page, 'Nova Lee', email, async () => {
        await page.goto(inviteUrl);
        await page.getByRole('link', { name: 'Create an account' }).click();
    });
    await page.waitForURL(inviteUrl);
    await expect(page.getByText('One step first: verify your email.')).toBeVisible();

    await page.goto(verificationLink(email));
    await page.waitForURL(inviteUrl);

    // The league goal, ready to use; then its questions, the plan, and the Clubhouse.
    await expect(page.getByText('Same goal as the league')).toBeVisible();
    await page.getByRole('button', { name: 'Use this goal' }).click();
    await page.waitForURL(/\/goals\/\d+\/intake\?from=joined/);
    await page.waitForLoadState('networkidle');
    await page.locator('button[name="skip"]:visible').first().click();
    await page.waitForURL(/\/goals\/\d+$/);
    await page.getByRole('button', { name: 'Accept this plan' }).click();
    await page.waitForLoadState('networkidle');
    await page.goto('/dashboard');
    await skipTour(page);
    await expect(page.getByText('Run a marathon').first()).toBeVisible();
    await expect(page.locator('[data-league-setup="needs_goal"]')).toHaveCount(0);
    expect(errors).toEqual([]);
    await context.close();
});

test('the scoring sheet opens from the standings and closes four ways without leaving the page', async ({ page }, testInfo) => {
    const errors = watchErrors(page);
    await startDemo(page);
    const league = await demoLeague(page);
    await page.goto(`${league}/standings`);
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => {
        window.__marker = 'still here';
    });
    const url = page.url();
    const sheet = page.locator('#scoring-sheet');
    const open = async () => {
        const button = page.locator('main [data-scoring-help]').first();
        // Opened from the keyboard (focus, then Enter): this test is about the four ways to close it.
        await button.focus();
        await page.keyboard.press('Enter');
        await expect(sheet).toHaveAttribute('open', '');
        await expect(sheet.locator('[data-band="in_line"]')).toBeVisible();
    };

    await open();
    await sheet.locator('button[aria-label="Close"]').click();
    await expect(sheet).not.toHaveAttribute('open', '');

    await open();
    await page.mouse.click(4, 4); // The dimmed area outside the sheet.
    await expect(sheet).not.toHaveAttribute('open', '');

    await open();
    await sheet.evaluate((dialog) => {
        const touch = (type, y) => {
            const event = new Event(type, { bubbles: true });
            Object.defineProperty(event, type === 'touchend' ? 'changedTouches' : 'touches', { value: [{ clientY: y }] });
            dialog.querySelector('[data-sheet-scroll]').dispatchEvent(event);
        };
        touch('touchstart', 200);
        touch('touchend', 360);
    });
    await expect(sheet).not.toHaveAttribute('open', '');

    await open();
    await page.goBack();
    await expect(sheet).not.toHaveAttribute('open', '');

    expect(page.url()).toBe(url);
    expect(await page.evaluate(() => window.__marker), 'no reload, never left the page').toBe('still here');
    expect(errors).toEqual([]);
    void testInfo;
});

test('Help opens at the right answer on three screens, and search filters the questions', async ({ page }) => {
    const errors = watchErrors(page);
    await startDemo(page);
    const league = await demoLeague(page);
    const help = page.locator('#help-sheet');

    for (const [path, focus] of [['/dashboard', 'when-check-in'], [`${new URL(league).pathname}/standings`, 'table'], ['/matchups', 'reading-matchup']]) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await page.locator('#app-topbar [data-open-help]').click();
        await expect(help).toHaveAttribute('open', '');
        await expect(help.locator(`#help-${focus}`)).toHaveAttribute('open', '');
        await help.locator('button[aria-label="Close"]').click();
        await expect(help).not.toHaveAttribute('open', '');
    }

    await page.locator('#app-topbar [data-open-help]').click();
    await help.locator('[data-help-search]').fill('dispute');
    await expect(help.locator('#help-disputes')).toBeVisible();
    await expect(help.locator('#help-what-is-goalline')).toBeHidden();
    await help.locator('[data-help-search]').fill('zzzz nothing');
    await expect(help.locator('[data-help-empty]')).toBeVisible();
    expect(errors).toEqual([]);
});

test('a new account is offered the tour once; it can be skipped or finished and replayed from Help', async ({ page }) => {
    const errors = watchErrors(page);
    await register(page, 'Tia Tour', unique('tia'));
    await finishWelcome(page);
    const tour = page.locator('[data-tour]');
    await expect(tour).toBeVisible({ timeout: 5000 });
    await expect(tour.locator('[data-tour-count]')).toHaveText(/^1 of \d$/);

    // Never blocking: the page behind still works, and focus isn't trapped.
    await expect(page.locator('.gl-tour-target')).toHaveCount(1);
    const steps = Number((await tour.locator('[data-tour-count]').textContent()).split(' of ')[1]);
    for (let step = 1; step < steps; step++) {
        await tour.locator('[data-tour-next]').click();
    }
    await expect(tour.locator('[data-tour-next]')).toHaveText('Done');
    await tour.locator('[data-tour-next]').click();
    await expect(tour).toBeHidden();

    await page.reload();
    await page.waitForTimeout(1200);
    await expect(tour).toBeHidden();

    // Replay from Help, and Escape ends it.
    await page.locator('#app-topbar [data-open-help]').click();
    await page.locator('#help-sheet [data-tour-start]').click();
    await expect(tour).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(tour).toBeHidden();
    expect(errors).toEqual([]);
});

test('no horizontal overflow on the new screens at 375, 390 and 430 px', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.use.isMobile, 'Phone widths only.');
    await startDemo(page);
    const league = new URL(await demoLeague(page)).pathname;

    for (const width of [375, 390, 430]) {
        await page.setViewportSize({ width, height: 844 });
        for (const path of [`${league}/standings`, `${league}/my-goal`, '/help', '/dashboard']) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            expect(overflow, `${path} at ${width}px`).toBe(false);
        }
        await page.locator('#app-topbar [data-open-help]').click();
        await expect(page.locator('#help-sheet')).toHaveAttribute('open', '');
        expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), `Help at ${width}px`).toBe(false);
        await page.locator('#help-sheet button[aria-label="Close"]').click();
    }
});
