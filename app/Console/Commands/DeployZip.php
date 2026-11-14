<?php

namespace App\Console\Commands;

use App\BuildStamp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Builds deploy.zip from a commit (git archive, so only committed files go in, with Linux-friendly paths) and adds
 * build.json at its root: the short commit hash and the build time, nothing secret. The live About page and /health
 * show them, so anyone can check the site matches the final commit.
 */
class DeployZip extends Command
{
    protected $signature = 'goalline:deploy-zip {ref=HEAD : The commit, branch or tag to build from} {--output=deploy.zip : Where to write the zip}';

    protected $description = 'Build deploy.zip from a commit, stamped with its short hash and build time';

    public function handle(): int
    {
        $ref = (string) $this->argument('ref');
        $output = (string) $this->option('output');

        $commit = Process::path(base_path())->run(['git', 'rev-parse', '--short', $ref.'^{commit}']);

        if (! $commit->successful()) {
            $this->error("Couldn't find the commit {$ref}.");

            return self::FAILURE;
        }

        $hash = trim($commit->output());
        $folder = storage_path('framework/deploy-stamp-'.bin2hex(random_bytes(4)));
        mkdir($folder, 0775, true);
        $stamp = $folder.DIRECTORY_SEPARATOR.BuildStamp::FILE;
        file_put_contents($stamp, json_encode(['commit' => $hash, 'built_at' => now()->utc()->toIso8601String()], JSON_PRETTY_PRINT).PHP_EOL);

        try {
            $archive = Process::path(base_path())->run(['git', 'archive', '--format=zip', '-o', $output, '--add-file='.$stamp, $ref]);
        } finally {
            @unlink($stamp);
            @rmdir($folder);
        }

        if (! $archive->successful()) {
            $this->error('git archive failed: '.trim($archive->errorOutput()));

            return self::FAILURE;
        }

        $this->info("{$output} built from {$hash}, stamped ".now()->utc()->format('Y-m-d H:i').' UTC.');

        return self::SUCCESS;
    }
}
