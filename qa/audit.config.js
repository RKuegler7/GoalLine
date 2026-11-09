// UI density audit (not the QA suite): phone 390×844 and desktop 1440×900, same isolated QA server and database.
import { defineConfig, devices } from '@playwright/test';
import base from '../playwright.config.js';

export default defineConfig({
    ...base,
    testDir: 'audit',
    outputDir: 'test-results-audit',
    webServer: { ...base.webServer, cwd: process.cwd() },
    reporter: [['list']],
    timeout: 6 * 60 * 1000,
    projects: [
        { name: 'phone', use: { ...devices['iPhone 14'], viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 } },
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
    ],
});
