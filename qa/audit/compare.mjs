// Before/after table for the UI density audit, with the quality bar checked: in-app titles at most 24px, content
// starting within 200px, no sideways scroll, and pages at most about two phone screens. Run `npm run audit:compare`
// after `npm run audit:after`; it writes qa/after/COMPARE.md (git-ignored) and prints it.
import fs from 'node:fs';
import path from 'node:path';

const read = (dir, profile) => {
    const file = path.resolve('qa', dir, profile, 'metrics.json');

    return fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : [];
};

const marketing = ['Login', 'Landing', 'About'];
let out = '# UI density: before and after\n\n';

for (const profile of ['phone', 'desktop']) {
    const before = read('before', profile);
    const after = read('after', profile);
    out += `## ${profile === 'phone' ? 'Phone (390×844)' : 'Desktop (1440×900)'}\n\n`;
    out += '| Page | Screens tall: before | after | Biggest heading | Content starts at | Checks |\n| --- | --- | --- | --- | --- | --- |\n';

    for (const row of after) {
        const old = before.find((each) => each.page === row.page) ?? {};
        const inApp = !marketing.includes(row.page);
        const flags = [];

        if (inApp && row.maxHeading > 24) flags.push(`heading ${row.maxHeading}px`);
        if (inApp && row.firstContent > 200) flags.push('content below 200px');
        if (inApp && profile === 'phone' && row.screens > 2.2) flags.push('over 2 screens');
        if (row.overflowX) flags.push('sideways scroll');

        out += `| ${row.page} | ${old.screens ?? '–'} | ${row.screens} | ${row.maxHeading}px | ${row.firstContent ?? '–'}px | ${inApp ? (flags.join(', ') || 'OK') : 'marketing page'} |\n`;
    }

    out += '\n';
}

fs.mkdirSync(path.resolve('qa/after'), { recursive: true });
fs.writeFileSync(path.resolve('qa/after/COMPARE.md'), out);
console.log(out);
