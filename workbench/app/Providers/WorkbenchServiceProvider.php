<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Boost\Install\GuidelineComposer;
use Laravel\Boost\Install\SkillComposer;
use Laravel\Boost\Support\Config;
use Laravel\Roster\Roster;
use Workbench\App\Support\BoostConfig;
use Workbench\App\Support\BoostGuidelineComposer;
use Workbench\App\Support\BoostSkillComposer;

use function Orchestra\Testbench\default_migration_path;
use function Orchestra\Testbench\package_path;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->readBoostConfigFromPackageRoot();
        $this->persistWorkbenchDatabaseToFile();
    }

    public function boot(): void
    {
        $this->pointBoostAtPackageRoot();
        $this->redirectBoostSkillsToPackageRoot();
        $this->loadOwnerMigrationsForServe();
    }

    // The served workbench needs a database that survives between requests and
    // restarts, so point it at a file under workbench/database/ instead of the
    // default in-memory connection. Tests keep their own in-memory database.
    private function persistWorkbenchDatabaseToFile(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        // Respect an explicit database chosen via the environment (the CalDAV
        // integration harness points DB_DATABASE at its own throwaway file).
        // DB_CONNECTION is set by Testbench itself, so only DB_DATABASE is a
        // reliable signal that the database was chosen deliberately.
        if (env('DB_DATABASE') !== null) {
            return;
        }

        $this->app->useDatabasePath(package_path('workbench/database'));

        $config = $this->app['config'];
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite.database', package_path('workbench/database/database.sqlite'));
    }

    // The package's DAV tables have foreign keys to the owner table, which a host
    // app would provide. Load Testbench's default migrations (users, cache, jobs)
    // so `workbench:build` can migrate a usable schema. Skipped under tests, which
    // load the owner table via tests/TestCase.
    private function loadOwnerMigrationsForServe(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $this->loadMigrationsFrom(default_migration_path());
    }

    // Boost reads boost.json and custom .ai guidelines and skills from
    // base_path(), which under Testbench is the vendor skeleton rather than the
    // package root.
    private function readBoostConfigFromPackageRoot(): void
    {
        if (! class_exists(Config::class)) {
            return;
        }

        $this->app->singleton(Config::class, fn (): Config => new BoostConfig);
        $this->app->bind(GuidelineComposer::class, BoostGuidelineComposer::class);
        $this->app->bind(SkillComposer::class, BoostSkillComposer::class);
    }

    // Boost binds Roster against base_path() in its own register(), so this must
    // run in boot() to win.
    private function pointBoostAtPackageRoot(): void
    {
        if (! class_exists(Roster::class)) {
            return;
        }

        $this->app->singleton(Roster::class, fn (): Roster => Roster::scan(package_path()));
    }

    // SkillWriter is instantiated directly (not via the container) and writes to
    // base_path($agent->skillsPath()). Steer its only configurable input back up
    // out of the skeleton to the package root with a relative path.
    private function redirectBoostSkillsToPackageRoot(): void
    {
        if (! class_exists(Roster::class)) {
            return;
        }

        $skeleton = ltrim(str_replace(package_path(), '', base_path()), '/');
        $upToPackageRoot = str_repeat('../', substr_count($skeleton, '/') + 1);

        config([
            'boost.agents.claude_code.skills_path' => $upToPackageRoot.'.claude/skills',
            'boost.agents.codex.skills_path' => $upToPackageRoot.'.agents/skills',
        ]);
    }
}
