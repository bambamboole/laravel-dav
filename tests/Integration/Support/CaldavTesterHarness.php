<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\InvokedProcess;
use RuntimeException;

/**
 * Boots the package's DAV server as a real, network-reachable HTTP process and
 * drives the external python `caldav-server-tester` against it, returning a
 * normalized feature/support map.
 *
 * The server runs in a separate OS process (orchestra/testbench `serve`) backed
 * by a temporary file SQLite database that this harness migrates and seeds. All
 * child processes are spawned through illuminate/process.
 *
 * Single responsibility: process lifecycle + invoking the tester. Result
 * shaping lives in {@see normalize()}; the comparison against the committed
 * baseline lives in the test itself.
 */
final class CaldavTesterHarness
{
    private ProcessFactory $process;

    private string $basePath;

    private string $tempDir;

    private string $databasePath;

    private int $port;

    /** @var array<string, string> */
    private array $env;

    private ?InvokedProcess $server = null;

    public function __construct()
    {
        $this->process = new ProcessFactory;
        $this->basePath = dirname(__DIR__, 3);
        $this->tempDir = $this->makeTempDir();
        $this->databasePath = $this->tempDir.'/dav.sqlite';
        $this->port = $this->findFreePort();
        $this->env = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->databasePath,
            'DAV_OWNER_MODEL' => OwnerUser::class,
            'DAV_REALM' => 'CalDAV Server Tester',
        ];
    }

    /**
     * Migrate + seed the temporary database, start the server, and block until
     * it answers HTTP requests.
     */
    public function boot(): void
    {
        touch($this->databasePath);

        $this->runTestbench(['migrate:fresh', '--no-interaction']);
        $this->runTestbench([
            'db:seed',
            '--class='.CaldavTesterSeeder::class,
            '--no-interaction',
        ]);

        $this->server = $this->process
            ->path($this->basePath)
            ->env($this->env)
            ->start([
                $this->basePath.'/vendor/bin/testbench',
                'serve',
                '--host=127.0.0.1',
                '--port='.$this->port,
            ]);

        $this->waitUntilReady();
    }

    /**
     * Run every available compatibility check against the booted server.
     *
     * The tester aborts the whole run when an individual check fails its own
     * internal self-consistency assertions. To capture the status quo for every
     * check anyway, we run all checks at once and, on a crash, isolate the
     * offending check from stderr, record it as "errored", exclude it, and retry
     * until the run completes cleanly.
     *
     * @throws \JsonException
     */
    public function runCompatibilityChecks(): CaldavTesterResult
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

            $result = $this->process
                ->path($this->basePath)
                ->timeout(600)
                ->run(array_merge([$this->testerBinary()], $arguments));

            if ($result->successful()) {
                return CaldavTesterResult::fromTesterOutput($result->output(), $excluded);
            }

            $crasher = $this->identifyCrashingCheck($result->errorOutput());

            if ($crasher === null || in_array($crasher, $excluded, true)) {
                throw new RuntimeException(
                    "caldav-server-tester crashed and the failing check could not be isolated.\n\n".
                    $result->errorOutput()
                );
            }

            $excluded[] = $crasher;
        }

        throw new RuntimeException('caldav-server-tester never produced a clean run after isolating crashing checks.');
    }

    /**
     * Stop the server and remove the temporary working directory. Safe to call
     * more than once and on a partially-booted harness.
     */
    public function shutdown(): void
    {
        if ($this->server !== null && $this->server->running()) {
            $this->server->stop();
        }
        $this->server = null;

        $this->removeDirectory($this->tempDir);
    }

    public function baseUrl(): string
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
            '--caldav-url', $this->baseUrl(),
            '--caldav-username', CaldavTesterFixture::USERNAME,
            '--caldav-password', CaldavTesterFixture::SECRET,
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
     * @param  list<string>  $arguments
     */
    private function runTestbench(array $arguments): void
    {
        $result = $this->process
            ->path($this->basePath)
            ->env($this->env)
            ->timeout(120)
            ->run(array_merge([$this->basePath.'/vendor/bin/testbench'], $arguments));

        if (! $result->successful()) {
            throw new RuntimeException(
                'testbench '.implode(' ', $arguments)." failed.\n\n".
                $result->output()."\n".$result->errorOutput()
            );
        }
    }

    private function waitUntilReady(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if ($this->server !== null && ! $this->server->running()) {
                throw new RuntimeException(
                    "DAV server process exited before becoming ready.\n\n".
                    $this->server->output()."\n".$this->server->errorOutput()
                );
            }

            $status = $this->httpStatus();
            if (in_array($status, [200, 207, 401], true)) {
                return;
            }

            usleep(200_000);
        }

        throw new RuntimeException('DAV server did not become ready within the timeout.');
    }

    private function httpStatus(): ?int
    {
        // Suppress the expected "connection refused" warning while the server is
        // still starting up; the @ operator alone does not stop PHPUnit's error
        // handler from turning it into a test warning.
        set_error_handler(static fn (): bool => true);

        try {
            $socket = fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.5);
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            return null;
        }

        fwrite($socket, "GET /dav/ HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");
        $statusLine = (string) fgets($socket);
        fclose($socket);

        if (preg_match('#^HTTP/\d\.\d (\d{3})#', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function testerBinary(): string
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

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/laravel-dav-tester-'.bin2hex(random_bytes(6));
        if (! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create temp directory: {$dir}");
        }

        return $dir;
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException("Could not allocate a free port: {$errstr}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        $port = (int) substr($name, strrpos($name, ':') + 1);
        if ($port <= 0) {
            throw new RuntimeException("Could not determine a free port from: {$name}");
        }

        return $port;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
