<?php

namespace Database\Seeders;

use App\Models\ProcessService;
use Illuminate\Database\Seeder;

class ProcessServiceSeeder extends Seeder
{
    public function run(): void
    {
        ProcessService::seedDefaults();
    }
}
