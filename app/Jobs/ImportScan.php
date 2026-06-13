<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class ImportScan implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public int $ownerId,
        public string $name = 'Imported',
        public string $disk = 'import',
    ) {
    }

    public function handle(): void
    {
        Artisan::call('files:import', [
            '--disk' => $this->disk,
            '--owner' => $this->ownerId,
            '--name' => $this->name,
        ]);
    }
}
