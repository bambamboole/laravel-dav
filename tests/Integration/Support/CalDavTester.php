<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\PendingProcess;
use PDO;
use RuntimeException;

/**
 * Boots the package's DAV server as a real, network-reachable HTTP process and
 * drives the external python `caldav-server-tester` against it, returning a
 * typed {@see CaldavTesterResult}.
 *
 * The server runs in a separate OS process (orchestra/testbench `serve`) backed
 * by a temporary file SQLite database that is migrated and seeded for the run.
 * All child processes are spawned through illuminate/process.
 *
 * Use the single entry point {@see runCompatibilityTests()}, which boots the
 * server, runs the tester, and always shuts everything down again.
 */
final class CalDavTester
{
    private ProcessFactory $process;

    private HttpFactory $http;

    private string $basePath;

    private string $testbenchBinary;

    private string $tempDir;

    private string $databasePath;

    private int $port;

    /** @var array<string, string> */
    private array $env;

    private ?string $testerBinary = null;

    private ?InvokedProcess $server = null;

    private ?string $lastReadinessProbe = null;

    /**
     * Boot the server, run the full compatibility suite, and tear everything
     * down again, returning the parsed result.
     *
     * @throws \JsonException
     */
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
        $this->http = new HttpFactory;
        $this->basePath = dirname(__DIR__, 3);
        $this->testbenchBinary = $this->basePath.'/vendor/bin/testbench';
        $this->tempDir = $this->makeTempDir();
        $this->databasePath = $this->tempDir.'/dav.sqlite';
        $this->port = $this->findFreePort();
        $this->env = [
            'APP_DEBUG' => 'true',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->databasePath,
            'DAV_OWNER_MODEL' => OwnerUser::class,
            'DAV_REALM' => 'CalDAV Server Tester',
            'LOG_CHANNEL' => 'stderr',
        ];
    }

    /**
     * Migrate + seed the temporary database, start the server, and block until
     * it answers HTTP requests.
     */
    private function boot(): void
    {
        touch($this->databasePath);
        $this->enableSqliteWalMode();

        $this->runTestbench([
            'migrate:fresh',
            '--seed',
            '--seeder='.CaldavTesterSeeder::class,
            '--no-interaction',
        ]);

        $this->server = $this->process
            ->path($this->serverPublicPath())
            ->env($this->serverEnv())
            ->start([
                PHP_BINARY,
                '-d',
                'variables_order=EGPCS',
                '-S',
                '127.0.0.1:'.$this->port,
                $this->serverRouterPath(),
            ]);

        $this->waitUntilReady();
        $this->warmCurrentUserPrincipal();
    }

    private function enableSqliteWalMode(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $journalMode = $pdo->query('pragma journal_mode = wal')->fetchColumn();

        if (! is_string($journalMode) || strtolower($journalMode) !== 'wal') {
            throw new RuntimeException("Could not enable SQLite WAL mode for {$this->databasePath}.");
        }
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
                    $result->errorOutput().
                    $this->serverOutput()
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
    private function shutdown(): void
    {
        if ($this->server !== null && $this->server->running()) {
            $this->server->stop();
        }
        $this->server = null;

        (new Filesystem)->deleteDirectory($this->tempDir);
    }

    private function baseUrl(): string
    {
        return "http://127.0.0.1:{$this->port}/dav/";
    }

    private function serverPublicPath(): string
    {
        return $this->basePath.'/vendor/orchestra/testbench-core/laravel/public';
    }

    private function serverRouterPath(): string
    {
        return $this->basePath.'/vendor/orchestra/testbench-core/laravel/server.php';
    }

    /**
     * @return array<string, string>
     */
    private function serverEnv(): array
    {
        return array_merge($this->env, [
            'TESTBENCH_WORKING_PATH' => $this->basePath,
            'TESTBENCH_USER_MODEL' => OwnerUser::class,
        ]);
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
     * A process builder rooted at the package with the shared SQLite/owner
     * environment applied, used for every spawned testbench command.
     */
    private function testbench(): PendingProcess
    {
        return $this->process->path($this->basePath)->env($this->env);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runTestbench(array $arguments): void
    {
        $result = $this->testbench()
            ->timeout(120)
            ->run(array_merge($this->testbenchCommand(), $arguments));

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
                    'DAV server process exited before becoming ready.'.
                    $this->serverOutput()
                );
            }

            if ($this->serverIsAnswering()) {
                return;
            }

            usleep(200_000);
        }

        throw new RuntimeException('DAV server did not become ready within the timeout.'.$this->serverOutput());
    }

    /**
     * @return list<string>
     */
    private function testbenchCommand(): array
    {
        return [
            PHP_BINARY,
            '-d',
            'variables_order=EGPCS',
            $this->testbenchBinary,
        ];
    }

    private function serverIsAnswering(): bool
    {
        try {
            $this->http
                ->connectTimeout(1)
                ->timeout(2)
                ->withoutRedirecting()
                ->get($this->baseUrl());

            return true;
        } catch (ConnectionException) {
            return false;
        }
    }

    private function warmCurrentUserPrincipal(): void
    {
        try {
            $this->lastReadinessProbe = $this->describeResponse($this->currentUserPrincipalResponse());
        } catch (ConnectionException $exception) {
            $this->lastReadinessProbe = 'Current-user-principal warmup failed to connect: '.$exception->getMessage();
        }
    }

    private function currentUserPrincipalResponse(): HttpResponse
    {
        return $this->http
            ->connectTimeout(1)
            ->timeout(2)
            ->withoutRedirecting()
            ->withBasicAuth(CaldavTesterFixture::USERNAME, CaldavTesterFixture::SECRET)
            ->withHeaders(['Depth' => '0'])
            ->withBody(<<<'XML'
                    <?xml version="1.0" encoding="utf-8" ?>
                    <d:propfind xmlns:d="DAV:">
                        <d:prop>
                            <d:current-user-principal />
                        </d:prop>
                    </d:propfind>
                    XML, 'application/xml')
            ->send('PROPFIND', $this->baseUrl());
    }

    private function describeResponse(HttpResponse $response): string
    {
        return "HTTP {$response->status()}\n".
            'Body: '.substr($response->body(), 0, 2_000);
    }

    private function serverOutput(): string
    {
        if ($this->server === null) {
            return '';
        }

        $sections = [];
        $stdout = trim($this->server->output());
        $stderr = trim($this->server->errorOutput());

        if ($stdout !== '') {
            $sections[] = "Server stdout:\n".$stdout;
        }

        if ($stderr !== '') {
            $sections[] = "Server stderr:\n".$stderr;
        }

        if ($this->lastReadinessProbe !== null) {
            $sections[] = "Last DAV warmup response:\n".$this->lastReadinessProbe;
        }

        return $sections === [] ? '' : "\n\n".implode("\n\n", $sections);
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
        $socket = socket_create_listen(0);
        if ($socket === false) {
            throw new RuntimeException('Could not allocate a free port: '.socket_strerror(socket_last_error()));
        }

        socket_getsockname($socket, $address, $port);
        socket_close($socket);

        return $port;
    }
}
