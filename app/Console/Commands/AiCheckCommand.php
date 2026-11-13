<?php

namespace App\Console\Commands;

use App\Services\AiCheck;
use Illuminate\Console\Command;

class AiCheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'goalline:ai-check';

    /**
     * @var string
     */
    protected $description = 'Make one tiny Claude API call with the configured key and say whether the AI coach works (never prints the key)';

    public function handle(AiCheck $check): int
    {
        $result = $check->run();

        $this->line($result['ok'] ? "<info>PASS</info>  {$result['message']}" : "<error>FAIL</error>  {$result['message']}");

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
