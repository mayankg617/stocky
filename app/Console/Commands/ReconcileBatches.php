<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReconcileBatches extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'batches:reconcile';

    /**
     * The console command description.
     */
    protected $description = 'Dummy command to fix missing class error';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('ReconcileBatches command executed.');
    }
}