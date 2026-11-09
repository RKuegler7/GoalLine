// Shared QA checks. Every screen visited gets: a screenshot, horizontal overflow, tap targets under 44 px (phones),
// text under 12 px, axe accessibility (alt text, form labels, contrast, names), console errors, failed requests and
// 500s. Actions that should update in place are checked for full page reloads and jumps to the top.
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';

const SCREENSHOTS = path.resolve('qa/screenshots');

export class Reviewer {
    constructor(page, testInfo) {
        this.page = page;
        this.testInfo = testInfo;
        this.profile = testInfo.project.name;
        this.mobile = Boolean(testInfo.project.use.isMobile);
        this.screen = 'Start';
        this.shot = 0;
        this.findings = [];

        page.on('console', (message) => {
            if (message.type() === 'error') {
                this.add('important', 'Console error', message.text().slice(0, 300), 'Fix the script or the resource behind this error.');
            }
        });
        page.on('pageerror', (error) => {
            // WebKit reports a same-site request it cancelled because the page navigated away as "due to access
            // control checks". It's the same as Chromium's ERR_ABORTED (ignored below), not a crash.
            const host = new URL(testInfo.project.use.baseURL ?? 'http://127.0.0.1').host;

            if (/due to access control checks/i.test(error.message) && error.message.includes(host)) {
                return;
            }

            this.add('blocker', 'JavaScript crash', error.message.slice(0, 300), 'An uncaught exception stops scripts on this page. Fix it first.');
        });
        page.on('response', (response) => {
            const status = response.status();

            if (status >= 500) {
                this.add('blocker', 'Server error', `${status} ${new URL(response.url()).pathname}`, 'Check storage/logs/laravel.log for the stack trace.');
            } else if (status >= 400 && ![401, 419, 422, 429].includes(status) && response.request().resourceType() !== 'document') {
                this.add('important', 'Failed request', `${status} ${new URL(response.url()).pathname}`, 'A file or request the page needs is missing.');
            }
        });
        page.on('requestfailed', (request) => {
            const failure = request.failure()?.errorText || '';

            if (!/ERR_ABORTED|cancelled|NS_BINDING_ABORTED/i.test(failure)) {
                this.add('important', 'Failed request', `${new URL(request.url()).pathname}: ${failure}`, 'The request never completed. Check the URL and the server.');
            }
        });
    }

    add(severity, rule, detail, fix) {
        this.findings.push({ severity, rule, detail, fix, screen: this.screen, profile: this.profile });
    }

    /**
     * Visit a screen (or review the current one) and run every check on it.
     */
    async review(screen, url = null) {
        this.screen = screen;

        if (url) {
            await this.page.goto(url);
        }

        await this.page.waitForLoadState('networkidle').catch(() => {});
        // A PHP error can come back with a 200, so look at the page itself too.
        const crash = await this.page.evaluate(() => document.body?.innerText.match(/(Fatal error|Parse error|Uncaught [A-Z]\w+|Whoops!|Internal Server Error).{0,160}/)?.[0]);

        if (crash) {
            this.add('blocker', 'PHP error on the page', crash, 'Check storage/logs/laravel.log and fix the error.');
        }

        await this.checkLayout();
        await this.checkAccessibility();
        await this.screenshot(screen);
    }

    async screenshot(label) {
        const folder = path.join(SCREENSHOTS, this.profile.replace(/[^a-z0-9]+/gi, '-').toLowerCase().replace(/^-+|-+$/g, ''));
        fs.mkdirSync(folder, { recursive: true });
        this.shot += 1;
        await this.page.screenshot({ path: path.join(folder, `${String(this.shot).padStart(2, '0')}-${label.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}.png`), fullPage: true });
    }

    async checkLayout() {
        const result = await this.page.evaluate((mobile) => {
            const visible = (el) => {
                const box = el.getBoundingClientRect();
                const style = getComputedStyle(el);

                return box.width > 0 && box.height > 0 && style.visibility !== 'hidden' && style.opacity !== '0' && !el.closest('[hidden], [aria-hidden="true"], .sr-only');
            };
            const label = (el) => (el.getAttribute('aria-label') || el.textContent || el.getAttribute('placeholder') || el.name || el.tagName).trim().replace(/\s+/g, ' ').slice(0, 50);
            const out = { overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, culprits: [], small: [], tiny: [] };
            const width = document.documentElement.clientWidth;

            if (out.overflow > 0) {
                document.querySelectorAll('body *').forEach((el) => {
                    if (el.getBoundingClientRect().right > width + 1 && visible(el) && !el.closest('[data-pager-track], [class*="overflow-x"]')
                        && ![...el.children].some((child) => child.getBoundingClientRect().right > width + 1)) {
                        out.culprits.push(`${el.tagName.toLowerCase()} "${label(el)}"`);
                    }
                });
            }

            if (mobile) {
                document.querySelectorAll('a[href], button, summary, select, input:not([type=hidden]), textarea, [role=button], [role=tab]').forEach((el) => {
                    if (!visible(el) || ['checkbox', 'radio', 'file'].includes(el.type)) {
                        return;
                    }

                    const box = el.getBoundingClientRect();
                    // A link inside a sentence is exempt (WCAG 2.5.8 inline exception).
                    if (el.tagName === 'A' && getComputedStyle(el).display === 'inline' && el.parentElement.closest('p')) {
                        return;
                    }

                    // The invisible tap area added on touch screens counts (.gl-tap::after).
                    const after = el.classList.contains('gl-tap') ? getComputedStyle(el, '::after') : null;
                    const w = Math.max(box.width, after ? parseFloat(after.width) || 0 : 0);
                    const h = Math.max(box.height, after ? parseFloat(after.height) || 0 : 0);

                    if (w < 44 || h < 44) {
                        out.small.push(`${el.tagName.toLowerCase()} "${label(el)}" ${Math.round(w)}×${Math.round(h)}`);
                    }
                });
            }

            const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) {
                const el = walker.currentNode.parentElement;

                if (walker.currentNode.textContent.trim() && el && visible(el) && parseFloat(getComputedStyle(el).fontSize) < 12) {
                    out.tiny.push(`"${walker.currentNode.textContent.trim().slice(0, 40)}" ${getComputedStyle(el).fontSize}`);
                }
            }

            out.small = [...new Set(out.small)];
            out.tiny = [...new Set(out.tiny)];

            return out;
        }, this.mobile);

        if (result.overflow > 0) {
            this.add('important', 'Horizontal overflow', `Page is ${result.overflow}px wider than the screen. Widest: ${result.culprits.slice(0, 3).join('; ')}`, 'Let the culprit shrink (min-w-0, minmax(0,1fr), break-words) or wrap.');
        }

        for (const target of result.small) {
            this.add('nice-to-have', 'Tap target under 44 px', target, 'Give it at least 44 × 44 px (padding, min-h-11) or a larger hit area.');
        }

        if (result.tiny.length) {
            this.add('nice-to-have', 'Text under 12 px', result.tiny.slice(0, 8).join('; '), 'Use at least 12 px (text-xs) for anything people need to read.');
        }
    }

    async checkAccessibility() {
        const results = await new AxeBuilder({ page: this.page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze()
            .catch(() => null);

        for (const violation of results?.violations ?? []) {
            const severity = ['critical', 'serious'].includes(violation.impact) ? 'important' : 'nice-to-have';
            const where = violation.nodes.slice(0, 3).map((node) => node.target.join(' ')).join(' | ');
            // For contrast, name the colour pairs: "#8a8f8b on #ffffff (3.4:1)" points straight at the class to fix.
            const pairs = [...new Set(violation.nodes.map((node) => node.any?.[0]?.data).filter((data) => data?.fgColor)
                .map((data) => `${data.fgColor} on ${data.bgColor} (${data.contrastRatio}:1)`))].slice(0, 4);
            const detail = `${violation.nodes.length} on screen, e.g. ${where}${pairs.length ? `. Colours: ${pairs.join('; ')}` : ''}`;
            this.add(severity, `Accessibility: ${violation.help}`, detail, violation.helpUrl);
        }
    }

    /**
     * Run an action that should update the page in place: no full reload, and no jump back to the top.
     */
    async inPlace(name, action) {
        const page = this.page;
        await page.evaluate(() => {
            window.__qaStillHere = true;
        });
        const before = await page.evaluate(() => window.scrollY);
        await action();
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(400);
        const after = await page.evaluate(() => ({ stillHere: window.__qaStillHere === true, y: window.scrollY }));

        if (!after.stillHere) {
            this.add('important', 'Full page reload', `"${name}" reloaded the whole page instead of updating in place.`, 'Mark the form data-live and put what it changes inside a data-region.');
        } else if (before > 300 && after.y < 50) {
            this.add('important', 'Jump to the top', `"${name}" scrolled from ${Math.round(before)}px back to the top.`, 'Keep the scroll position: update in place instead of redirecting to the top.');
        }
    }

    /**
     * Run a step of the demo script. A step that fails is a blocker, but the script carries on where it can.
     */
    async step(name, run) {
        this.screen = name;

        try {
            await run();
        } catch (error) {
            this.add('blocker', 'Demo step failed', `${name}: ${String(error.message).split('\n')[0].slice(0, 240)}`, 'This step of the demo script doesn\'t work. See the screenshot and trace.');
            await this.screenshot(`FAILED ${name}`).catch(() => {});
        }
    }

    async finish() {
        await this.testInfo.attach('qa-findings', { body: JSON.stringify(this.findings), contentType: 'application/json' });
    }
}
