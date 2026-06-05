<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\ProcessResult;
use RuntimeException;
use Throwable;

use function Amp\delay;

final class CalDavTester
{
    private ProcessFactory $process;

    private string $basePath;

    private int $port;

    private ?string $testerBinary = null;

    private ?string $configFile = null;

    private ?InProcessLaravelServer $server = null;

    /** @throws \JsonException */
    public static function runCompatibilityTests(): CaldavTesterResult
    {
        $tester = new self;

        try {
            $tester->boot();

            return $tester->runChecks();
        } finally {
            $tester->shutdown();
        }
    }

    private function __construct()
    {
        $this->process = new ProcessFactory;
        $this->basePath = dirname(__DIR__, 3);
        $this->port = $this->findFreePort();

        config([
            'app.debug' => true,
            'dav.owner_model' => OwnerUser::class,
            'dav.realm' => 'CalDAV Server Tester',
        ]);
    }

    private function boot(): void
    {
        (new CaldavTesterSeeder)->run();

        $this->server = new InProcessLaravelServer('127.0.0.1', $this->port);
        $this->server->start();

        $this->configFile = $this->writeTesterConfig();
    }

    /**
     * Write a python-caldav JSON config file describing both seeded accounts.
     * Pointing the tester at it via the CALDAV_CONFIG_FILE env var (and naming
     * both sections) is what enables the multi-user scheduling checks.
     */
    private function writeTesterConfig(): string
    {
        $account = fn (string $username): array => [
            'caldav_url' => $this->baseUrl(),
            'caldav_username' => $username,
            'caldav_password' => CaldavTesterFixture::SECRET,
            'calendar_name' => CaldavTesterFixture::CALENDAR_DISPLAY_NAME,
        ];

        $config = [
            CaldavTesterFixture::PRIMARY_SECTION => $account(CaldavTesterFixture::USERNAME),
            CaldavTesterFixture::SECONDARY_SECTION => $account(CaldavTesterFixture::SECOND_USERNAME),
        ];

        $path = sys_get_temp_dir().'/laravel-dav-caldav-tester-'.$this->port.'.json';
        file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $path;
    }

    /**
     * The tester aborts the whole run when an individual check fails its own
     * internal self-consistency assertions. To capture the status quo for every
     * check anyway, we run all checks at once and, on a crash, isolate the
     * offending check from stderr, record it as "errored", exclude it, and retry
     * until the run completes cleanly.
     *
     * @throws \JsonException
     */
    private function runChecks(): CaldavTesterResult
    {
        $checks = $this->listChecks();
        $excluded = [];
        $maxRounds = count($checks) + 1;

        for ($round = 0; $round < $maxRounds; $round++) {
            $selected = array_values(array_diff($checks, $excluded));

            $arguments = $this->testerBaseArguments();
            foreach ($selected as $check) {
                $arguments[] = '--run-checks';
                $arguments[] = $check;
            }
            $arguments[] = '--format';
            $arguments[] = 'json';

            $result = $this->runTester($arguments);

            if ($result->successful()) {
                return CaldavTesterResult::fromTesterOutput($result->output(), $excluded);
            }

            $crasher = $this->identifyCrashingCheck($result->errorOutput());

            if ($crasher === null || in_array($crasher, $excluded, true)) {
                throw new RuntimeException(
                    "caldav-server-tester crashed and the failing check could not be isolated.\n\n".
                    $result->errorOutput().
                    $this->serverOutput()
                );
            }

            $excluded[] = $crasher;
        }

        throw new RuntimeException('caldav-server-tester never produced a clean run after isolating crashing checks.');
    }

    private function shutdown(): void
    {
        if ($this->server instanceof InProcessLaravelServer) {
            $this->server->stop();
        }

        $this->server = null;

        if ($this->configFile !== null && file_exists($this->configFile)) {
            @unlink($this->configFile);
        }

        $this->configFile = null;
    }

    private function baseUrl(): string
    {
        return "http://127.0.0.1:{$this->port}/dav/";
    }

    /**
     * @return list<string>
     */
    private function listChecks(): array
    {
        $result = $this->process
            ->path($this->basePath)
            ->run([$this->testerBinary(), '--list-checks']);

        if (! $result->successful()) {
            throw new RuntimeException("Could not list caldav-server-tester checks.\n\n".$result->errorOutput());
        }

        $checks = preg_split('/\R/', trim($result->output())) ?: [];

        return array_values(array_filter(array_map('trim', $checks)));
    }

    /**
     * @return list<string>
     */
    private function testerBaseArguments(): array
    {
        return [
            '--config-section', CaldavTesterFixture::PRIMARY_SECTION,
            '--config-section', CaldavTesterFixture::SECONDARY_SECTION,
            '--caldav-calendar', CaldavTesterFixture::CALENDAR_DISPLAY_NAME,
        ];
    }

    private function identifyCrashingCheck(string $stderr): ?string
    {
        if (preg_match('/(\w+) (?:failed to check declared features|checked undeclared features)/', $stderr, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function testerCommand(array $arguments): array
    {
        return array_merge([$this->testerBinary()], $arguments);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runTester(array $arguments): ProcessResult
    {
        $process = $this->process
            ->path($this->basePath)
            ->env(['CALDAV_CONFIG_FILE' => (string) $this->configFile])
            ->timeout(600)
            ->start($this->testerCommand($arguments));

        while ($process->running()) {
            $process->ensureNotTimedOut();
            delay(0.01);
        }

        return $process->wait();
    }

    private function serverOutput(): string
    {
        if (! $this->server instanceof InProcessLaravelServer || ! $this->server->lastThrowable() instanceof Throwable) {
            return '';
        }

        $throwable = $this->server->lastThrowable();

        return "\n\nServer exception:\n".$throwable::class.': '.$throwable->getMessage()."\n".$throwable->getTraceAsString();
    }

    private function testerBinary(): string
    {
        return $this->testerBinary ??= $this->resolveTesterBinary();
    }

    private function resolveTesterBinary(): string
    {
        $override = getenv('CALDAV_SERVER_TESTER_BIN');
        if (is_string($override) && $override !== '' && is_executable($override)) {
            return $override;
        }

        $candidates = [
            (getenv('HOME') ?: '').'/.local/bin/caldav-server-tester',
            '/opt/homebrew/bin/caldav-server-tester',
            '/usr/local/bin/caldav-server-tester',
        ];
        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            $path = rtrim($dir, '/').'/caldav-server-tester';
            if (is_executable($path)) {
                return $path;
            }
        }

        throw new RuntimeException(
            'caldav-server-tester is not installed. Install it (e.g. `uv tool install caldav-server-tester`) '.
            'or set CALDAV_SERVER_TESTER_BIN to its path.'
        );
    }

    private function findFreePort(): int
    {
        $socket = socket_create_listen(0);
        if ($socket === false) {
            throw new RuntimeException('Could not allocate a free port: '.socket_strerror(socket_last_error()));
        }

        socket_getsockname($socket, $address, $port);
        socket_close($socket);

        return $port;
    }
}
