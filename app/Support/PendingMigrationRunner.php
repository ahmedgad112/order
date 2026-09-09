<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PendingMigrationRunner
{
    public function __construct(private readonly Application $app) {}

    public function run(): bool
    {
        if ($this->app->environment('testing') || $this->app->runningInConsole()) {
            return false;
        }

        try {
            if (! $this->hasPendingMigrations()) {
                return false;
            }

            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('optimize:clear');

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Pending migrations failed after deploy: '.$exception->getMessage());

            return false;
        }
    }

    private function hasPendingMigrations(): bool
    {
        $migrator = $this->app->make('migrator');

        if (! $migrator instanceof Migrator || ! $migrator->repositoryExists()) {
            return false;
        }

        $files = $migrator->getMigrationFiles(array_merge(
            $migrator->paths(),
            [$this->app->databasePath('migrations')],
        ));

        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        return $pending !== [];
    }
}
