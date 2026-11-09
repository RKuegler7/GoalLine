// Writes qa/REPORT.md from the findings each profile collected: grouped by severity (blocker, important,
// nice-to-have), merged across profiles, with the screen, the profiles and a suggested fix.
import fs from 'node:fs';
import path from 'node:path';

const ORDER = ['blocker', 'important', 'nice-to-have'];
const TITLES = { blocker: 'Blockers', important: 'Important', 'nice-to-have': 'Nice to have' };

export default class QaReporter {
    constructor() {
        this.findings = [];
        this.runs = [];
        this.started = new Date();
    }

    onTestEnd(test, result) {
        const profile = test.parent?.project()?.name ?? 'unknown';
        this.runs.push({ profile, status: result.status, duration: result.duration });

        for (const attachment of result.attachments) {
            if (attachment.name === 'qa-findings' && attachment.body) {
                this.findings.push(...JSON.parse(attachment.body.toString()));
            }
        }

        if (result.status !== 'passed' && !this.findings.some((finding) => finding.profile === profile && finding.severity === 'blocker')) {
            this.findings.push({
                severity: 'blocker',
                rule: 'Suite failed',
                detail: (result.error?.message || result.status).split('\n')[0].slice(0, 300),
                fix: 'Open the trace in qa/test-results to see where it stopped.',
                screen: 'Demo script',
                profile,
            });
        }
    }

    onEnd() {
        if (!this.runs.length) {
            return; // Nothing ran (playwright test --list, say): keep the last report.
        }

        // The same finding on several profiles is one row, listing the profiles.
        const merged = new Map();

        for (const finding of this.findings) {
            const key = [finding.severity, finding.rule, finding.screen, finding.detail].join('|');
            const row = merged.get(key) ?? { ...finding, profiles: new Set() };
            row.profiles.add(finding.profile);
            merged.set(key, row);
        }

        const rows = [...merged.values()];
        const count = (severity) => rows.filter((row) => row.severity === severity).length;
        const lines = [
            '# GoalLine QA report',
            '',
            `Generated ${this.started.toISOString().replace('T', ' ').slice(0, 16)} UTC by \`npm run qa\`. Screenshots are in \`qa/screenshots/\` (not committed).`,
            '',
            '| Profile | Demo script | Time |',
            '| --- | --- | --- |',
            ...this.runs.map((run) => `| ${run.profile} | ${run.status === 'passed' ? 'Passed' : 'Failed'} | ${Math.round(run.duration / 1000)}s |`),
            '',
            `**${count('blocker')} blockers, ${count('important')} important, ${count('nice-to-have')} nice to have.**`,
            '',
            'Checks on every screen: failed requests and 500s, console errors, horizontal overflow, tap targets under 44 px (phones), text under 12 px, axe accessibility (alt text, form labels, contrast, names), and in-place actions that reload the page or jump to the top.',
            '',
            '> WebKit in Playwright is close to iPhone Safari but it isn\'t a real iPhone: no real touch physics, home indicator, Safari toolbars, or iOS photo picker. Use the checklist at the end on a real phone before a release.',
            '',
        ];

        for (const severity of ORDER) {
            const group = rows.filter((row) => row.severity === severity);
            lines.push(`## ${TITLES[severity]} (${group.length})`, '');

            if (!group.length) {
                lines.push('None.', '');
                continue;
            }

            lines.push('| Screen | Profiles | Finding | Suggested fix |', '| --- | --- | --- | --- |');

            for (const row of group.sort((a, b) => a.rule.localeCompare(b.rule) || a.screen.localeCompare(b.screen))) {
                const cell = (text) => String(text).replace(/\|/g, '\\|').replace(/\n/g, ' ');
                lines.push(`| ${cell(row.screen)} | ${cell([...row.profiles].join(', '))} | **${cell(row.rule)}**: ${cell(row.detail)} | ${cell(row.fix)} |`);
            }

            lines.push('');
        }

        lines.push(
            '## Real iPhone checklist',
            '',
            'Run through this on an actual iPhone in Safari, then again after "Add to Home Screen":',
            '',
            '- [ ] Try the demo opens the Clubhouse in one tap, no zoom on load.',
            '- [ ] Tapping any text field does not zoom the page.',
            '- [ ] The bottom tab bar and Feedback button sit above the home indicator; nothing hides behind Safari\'s toolbar.',
            '- [ ] Check in a task: the circle fills in place, the toast shows, the page doesn\'t jump.',
            '- [ ] Show your work: the photo picker offers Photos and Camera; a photo uploads with the progress bar and turns Verified.',
            '- [ ] Matchup pager: swipe left and right, the counter and dots follow, the player toggle sticks to the top.',
            '- [ ] Tap a task on the pager: the bottom sheet slides up and closes with a tap outside it.',
            '- [ ] Rotate to landscape: content stays clear of the notch.',
            '- [ ] From the home screen: GoalLine opens full screen with the GL icon and green status bar.',
            '',
        );

        fs.mkdirSync(path.resolve('qa'), { recursive: true });
        fs.writeFileSync(path.resolve('qa/REPORT.md'), lines.join('\n'));
        console.log(`\nQA report: qa/REPORT.md (${count('blocker')} blockers, ${count('important')} important, ${count('nice-to-have')} nice to have)`);
    }
}
