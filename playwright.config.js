// GoalLine QA suite: the Try the demo script on an iPhone 14 (WebKit), a small Android phone and a desktop, with
// automatic checks on every screen. Run with `npm run qa`; the findings land in qa/REPORT.md.
import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const port = Number(process.env.QA_PORT || 8123);
const database = path.resolve('database/qa.sqlite');

export default defineConfig({
    testDir: 'qa/e2e',
    outputDir: 'qa/test-results',
    // PHP's built-in server answers one request at a time, so the profiles take turns.
    workers: 1,
    fullyParallel: false,
    timeout: 6 * 60 * 1000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['./qa/reporter.js']],
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'iPhone 14 (WebKit)', use: { ...devices['iPhone 14'] } },
        { name: 'Small Android (Chromium)', use: { ...devices['Galaxy S8'] } },
        { name: 'Desktop (Chromium)', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } } },
    ],
    // Its own fresh SQLite database, so QA never touches your local data. Mail goes to the log. qa/serve.js runs PHP's
    // server directly (artisan serve drops these overrides for keys already in .env).
    webServer: {
        command: 'node qa/serve.js',
        url: `http://127.0.0.1:${port}/login`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            APP_URL: `http://127.0.0.1:${port}`,
            QA_PORT: String(port),
            DEMO_STARTS_PER_HOUR: '1000',
            MAIL_MAILER: 'log',
            // The navigation spec signs up a brand-new member (with no GoalLeague) on this throwaway database.
            BETA_INVITE_ONLY: 'false',
            QUEUE_CONNECTION: 'sync',
            // New QA accounts aren't demo accounts: no live AI (the built-in questions and starter plan stand in), no spend.
            ANTHROPIC_API_KEY: '',
            // Laravel Boost's dev-only browser log sender isn't part of the app; keep it out of the findings.
            BOOST_BROWSER_LOGS_WATCHER: 'false',
        },
    },
});
