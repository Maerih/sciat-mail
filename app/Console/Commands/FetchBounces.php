<?php

namespace App\Console\Commands;

use App\Models\BounceHandler;
use App\Services\Bounces\BounceProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bounces:fetch')]
#[Description('Read bounce and complaint reports from every active bounce handler mailbox')]
class FetchBounces extends Command
{
    public function handle(BounceProcessor $processor): int
    {
        BounceHandler::where('status', 'active')->each(function (BounceHandler $handler) use ($processor) {
            $stats = $processor->process($handler);
            $this->line("{$handler->name}: {$stats['read']} read, {$stats['bounces']} bounces, {$stats['complaints']} complaints, {$stats['unmatched']} unmatched");
        });

        return self::SUCCESS;
    }
}
