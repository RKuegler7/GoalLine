// The full demo script, as a judge would click through it, on each profile. Each step is checked and screenshotted.
import { expect, test } from '@playwright/test';
import path from 'node:path';
import { Reviewer } from './support.js';

const PROOF = path.resolve('qa/fixtures/proof.png');

test('Try the demo, end to end', async ({ page }, testInfo) => {
    const qa = new Reviewer(page, testInfo);
    const mobile = qa.mobile;

    try {
        // The one step everything else needs.
        await test.step('Try the demo', async () => {
            await qa.review('Home', '/');
            // Building a demo world takes a few seconds (longer on a slow machine), so allow it more than a click.
            await Promise.all([
                page.waitForURL('**/dashboard', { timeout: 150_000 }),
                page.locator('a[href$="/demo"]').first().click({ timeout: 150_000 }),
            ]);
            await page.evaluate(() => fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }));
            await page.goto(page.url());
        });

        await test.step('Dashboard', () => qa.step('Dashboard', () => qa.review('Dashboard')));

        let goalUrl = null;
        await test.step('Open a goal and its plan', () => qa.step('Goal and plan', async () => {
            goalUrl = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((href) => /\/goals\/\d+$/.test(href)));
            expect(goalUrl, 'a goal link on the dashboard').toBeTruthy();
            await qa.review('Goal', goalUrl);
            await qa.review('Plan my week', '/plan');
            await qa.review('Today', '/today');
        }));

        await test.step('Check in a task', () => qa.step('Check in a task', async () => {
            await page.goto(goalUrl);
            const open = page.locator('form[data-optimistic="check"] button[data-check="open"]:visible').first();
            await open.scrollIntoViewIfNeeded();
            const key = await open.evaluate((button) => button.closest('form').dataset.liveKey);
            await page.evaluate(() => window.scrollBy(0, 120));
            await qa.inPlace('Check in a task', () => open.click());
            await expect(page.locator(`form[data-live-key="${key}"] [data-check="done"]`)).toBeVisible();
            await qa.review('Goal after a check-in');
        }));

        await test.step('Check in from the sheet', () => qa.step('Check-in sheet', async () => {
            // The floating Check in button works from any screen, without leaving it.
            await page.goto('/leagues');
            await page.locator('[data-checkin-open]').click();
            const sheet = page.locator('dialog[data-checkin-sheet][open]');
            await expect(sheet.locator('[data-region="checkin-sheet"]')).toBeVisible();
            const open = sheet.locator('form[data-optimistic="check"] button[data-check="open"]').first();

            if (await open.count()) {
                const key = await open.evaluate((button) => button.closest('form').dataset.liveKey);
                await qa.inPlace('Check in from the sheet', () => open.click());
                await expect(sheet.locator(`form[data-live-key="${key}"] [data-check="done"]`)).toBeVisible();
                await expect(page).toHaveURL(/\/leagues$/);
            }

            await qa.screenshot('Check-in sheet');
            await page.keyboard.press('Escape');
            // Closing the sheet steps history back (sheets.js); let that finish before loading the next page.
            await expect(page.locator('dialog[data-checkin-sheet][open]')).toHaveCount(0);
            await page.waitForLoadState('networkidle');
            await page.goto(goalUrl);
        }));

        await test.step('Attach proof', () => qa.step('Attach proof', async () => {
            const panel = page.locator('[data-proof-panel]:visible').first();

            if (!(await panel.count())) {
                await page.locator('[data-open-proof]:visible').first().click();
            }

            const form = page.locator('[data-proof-panel]:visible form').first();
            await form.locator('input[type=file]').setInputFiles(PROOF);
            await expect(form.locator('[data-photo-preview]')).toBeVisible();
            await qa.inPlace('Attach proof', () => form.locator('button[type=submit]').click());
            await expect(page.locator('.gl-toasts')).toContainText('Verified');
            await qa.review('Goal after proof');
        }));

        let leagueUrl = null;
        await test.step('Matchup pager', () => qa.step('Matchup pager', async () => {
            await qa.review('GoalLeagues', '/leagues');
            leagueUrl = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((href) => /\/leagues\/\d+$/.test(href)));
            await qa.review('GoalLeague', leagueUrl);
            await qa.review('Matchup pager', `${leagueUrl}/matchups`);
            await expect(page.locator('[data-pager-count]')).toHaveText(/^1 of \d+$/);

            if (mobile) {
                // A swipe: the track scrolls one page and snaps.
                await page.locator('[data-pager-track]').evaluate((track) => track.scrollBy({ left: track.clientWidth, behavior: 'instant' }));
            } else {
                await page.locator('[data-pager-next]').click();
            }

            await expect(page.locator('[data-pager-count]')).toHaveText(/^2 of \d+$/);
            await qa.screenshot('Matchup pager, page 2');
        }));

        await test.step('Open a task sheet', () => qa.step('Task sheet', async () => {
            await page.locator('[data-pager-prev]').click();
            await expect(page.locator('[data-pager-count]')).toHaveText(/^1 of \d+$/);
            await page.locator('[data-pager-page]').first().locator('[data-task-sheet-open]:visible').first().click();
            await expect(page.locator('dialog[data-task-sheet][open]')).toBeVisible();
            await qa.review('Task sheet');
            await page.keyboard.press('Escape');
            await expect(page.locator('dialog[data-task-sheet][open]')).toHaveCount(0);
        }));

        await test.step('Dispute a check-in', () => qa.step('Dispute a check-in', async () => {
            const firstPage = page.locator('[data-pager-page]').first();
            await firstPage.locator('details:has(> summary:has-text("This week\'s check-ins")) > summary').click();
            const dispute = firstPage.locator('details:has(> summary:text-is("Dispute"))').first();
            // Opened from the keyboard: inside the swipeable pager, Playwright's WebKit "scroll into view" fights the
            // scroll snapping and never settles (a real phone doesn't), so the click would time out.
            await dispute.locator('summary').evaluate((summary) => summary.scrollIntoView({ block: 'center' }));
            await dispute.locator('summary').focus();
            await page.keyboard.press('Enter');
            await expect(dispute.locator('input[name=reason]')).toBeVisible();
            await dispute.locator('input[name=reason]').fill('Can you show the route?');
            await dispute.locator('button[type=submit]').click();
            await page.waitForLoadState('networkidle');
            await expect(firstPage.locator('span:text-is("Disputed"):visible').first()).toBeVisible();
            await qa.review('Matchup after a dispute');
        }));

        await test.step('Vote on a group task', () => qa.step('Vote on a group task', async () => {
            await page.goto('/leagues');
            const marathon = page.locator('a[href*="/leagues/"]', { hasText: 'Marathon by March' }).first();
            await marathon.click();
            const yes = page.locator('form:has(input[name=approve][value="1"]) button[type=submit]').first();
            await yes.scrollIntoViewIfNeeded();
            await page.evaluate(() => window.scrollBy(0, 120));
            await qa.inPlace('Vote on a group task', () => yes.click());
            await expect(page.getByText(/Passed|You voted/i).first()).toBeVisible();
            await qa.review('GoalLeague after a vote');
        }));

        await test.step('Send a friend request', () => qa.step('Send a friend request', async () => {
            await qa.review('Friends', '/friends');
            await qa.review('People search', '/people/search?q=Tim');
            const add = page.locator('button', { hasText: 'Add friend' }).first();
            await qa.inPlace('Send a friend request', () => add.click());
            await expect(page.locator('button', { hasText: 'Request sent' }).first()).toBeVisible();
        }));

        await test.step('Copy a link', () => qa.step('Copy a link', async () => {
            // Every copied link uses the same share component; the calendar link is always there in the demo.
            const chromium = testInfo.project.name.includes('Chromium');

            if (chromium) {
                await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
            }

            await qa.review('Notifications and calendar', '/settings/notifications');
            const field = page.locator('#calendar-url');
            await page.locator('[data-copy-from="calendar-url"]').click();
            await expect(page.locator('.gl-toasts')).toContainText(/Link copied|Copying is blocked/);

            if (chromium) {
                const copied = await page.evaluate(() => navigator.clipboard.readText());
                expect(copied).toBe(await field.inputValue());
                expect(copied).toMatch(/^https?:\/\/[^/]+\/calendar\/.+/);
            }
        }));

        await test.step('More screens', () => qa.step('More screens', async () => {
            await qa.review('Highlights', '/highlights');
            await qa.review('Inbox', '/inbox');
            await qa.review('My Locker', '/profile');
        }));

        await test.step('Privacy page', () => qa.step('Privacy page', () => qa.review('Privacy and safety', '/privacy')));

        await test.step('About page', () => qa.step('About page', async () => {
            await qa.review('About', '/about');
            // The FAQ opens from the keyboard.
            const question = page.locator('#faq summary').first();
            await question.focus();
            await page.keyboard.press('Enter');
            await expect(page.locator('#faq details').first()).toHaveAttribute('open', '');
        }));
    } finally {
        await qa.finish();
    }

    expect(qa.findings.filter((finding) => finding.severity === 'blocker'), 'blockers').toEqual([]);
});
