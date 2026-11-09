// Starts the QA server for Playwright: a fresh qa.sqlite, migrated, then PHP's built-in server from public/ (as
// artisan serve does). Its request log goes to qa/server.log rather than back through Playwright's pipe, which
// can fill up and stall the server mid-request.
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const port = process.env.QA_PORT || '8123';
const root = path.resolve('.');

fs.closeSync(fs.openSync(process.env.DB_DATABASE, 'a'));

const migrate = spawnSync('php', ['artisan', 'migrate:fresh', '--force', '--no-interaction'], { cwd: root, stdio: 'inherit' });

if (migrate.status !== 0) {
    process.exit(migrate.status ?? 1);
}

const log = fs.openSync(path.join(root, 'qa', 'server.log'), 'w');
const server = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: path.join(root, 'public'),
    stdio: ['ignore', log, log],
});

const stop = () => server.kill();
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
process.on('exit', stop);
server.on('exit', (code) => process.exit(code ?? 0));
