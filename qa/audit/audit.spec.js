// UI density audit: screenshots every main screen at phone and desktop size and measures how tall each page is,
// how big its biggest heading is, and where the first real content starts. Run with `npm run audit:before` or
// `npm run audit:after`; results land in qa/before or qa/after (git-ignored).
import { test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const OUT = path.resolve('qa', process.env.AUDIT_DIR || (process.env.npm_lifecycle_event === 'audit:after' ? 'after' : 'before'));

async function measure(page) {
    return page.evaluate(() => {
        const viewport = window.innerHeight;
        const height = document.documentElement.scrollHeight;
        const main = document.querySelector('main') || document.body;
        const headings = [...document.querySelectorAll('h1, h2, h3')].filter((el) => el.offsetParent !== null);
        const inApp = document.body.hasAttribute('data-signed-in');
        const maxHeading = Math.max(0, ...headings.map((el) => parseFloat(getComputedStyle(el).fontSize)));
        const paragraph = [...main.querySelectorAll('p, li, td')].find((el) => el.offsetParent !== null && el.textContent.trim().length > 20);
        // First meaningful content: the first card, list, form or table in main (not a hero banner).
        const first = main.querySelector('[data-first-content], form, ul, ol, table, section, article');
        const firstTop = first ? Math.round(first.getBoundingClientRect().top + window.scrollY) : null;

        return {
            height,
            screens: Math.round((height / viewport) * 10) / 10,
            maxHeading: Math.round(maxHeading),
            body: paragraph ? Math.round(parseFloat(getComputedStyle(paragraph).fontSize) * 10) / 10 : null,
            firstContent: firstTop,
            overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            inApp,
        };
    });
}

test('UI audit', async ({ page }, testInfo) => {
    const profile = testInfo.project.name;
    const folder = path.join(OUT, profile);
    fs.mkdirSync(folder, { recursive: true });
    const results = [];
    let shot = 0;

    const capture = async (label, url) => {
        if (url) {
            await page.goto(url);
        }

        await page.waitForLoadState('networkidle').catch(() => {});
        shot += 1;
        const file = path.join(folder, `${String(shot).padStart(2, '0')}-${label.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}.png`);
        await page.screenshot({ path: file, fullPage: true }).catch(() => page.screenshot({ path: file }));
        results.push({ page: label, ...(await measure(page)) });
    };

    const firstLink = (pattern) => page.evaluate((source) => [...document.querySelectorAll('a[href]')].map((a) => a.href).find((href) => new RegExp(source).test(href)), pattern);

    await capture('Login', '/login');
    await capture('Landing', '/');
    await capture('About', '/about');

    await page.goto('/demo');
    await page.waitForURL('**/dashboard', { timeout: 90_000 });
    await capture('Clubhouse (home)');
    const goalUrl = await firstLink('/goals/\\d+$');
    await capture('Today', '/today');
    await capture('Plan my week', '/plan');
    await capture('Goal and plan', goalUrl);
    await capture('GoalLeagues and challenges', '/leagues');
    const leagueUrl = await firstLink('/leagues/\\d+$');
    const challengeUrl = await firstLink('/challenges/\\d+$');
    await capture('GoalLeague', leagueUrl);
    await capture('Matchups', `${leagueUrl}/matchups`);

    if (challengeUrl) {
        await capture('Challenge', challengeUrl);
    }

    await capture('Highlights', '/highlights');
    await capture('Friends', '/friends');
    await capture('People search', '/people/search?q=Tim');
    await capture('Profile (My Locker)', '/profile');
    await capture('Inbox', '/inbox');

    fs.writeFileSync(path.join(folder, 'metrics.json'), JSON.stringify(results, null, 2));
});
