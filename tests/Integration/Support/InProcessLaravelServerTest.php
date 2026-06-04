<?php

use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterFixture;
use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterSeeder;
use Bambamboole\LaravelDav\Tests\Integration\Support\InProcessLaravelServer;
use Symfony\Component\Process\Process;

use function Amp\delay;

it('serves the testbench app in process while an external client runs', function (): void {
    (new CaldavTesterSeeder)->run();

    $server = new InProcessLaravelServer('127.0.0.1', freeIntegrationPort());

    try {
        $server->start();

        $process = new Process([
            PHP_BINARY,
            '-r',
            <<<'PHP'
            $url = $argv[1];
            $username = $argv[2];
            $password = $argv[3];
            $context = stream_context_create([
                'http' => [
                    'method' => 'PROPFIND',
                    'header' => [
                        'Authorization: Basic '.base64_encode($username.':'.$password),
                        'Content-Type: application/xml',
                        'Depth: 0',
                    ],
                    'content' => '<?xml version="1.0" encoding="utf-8" ?><d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal /></d:prop></d:propfind>',
                    'ignore_errors' => true,
                ],
            ]);

            echo file_get_contents($url, false, $context);
            PHP,
            $server->url().'/dav/',
            CaldavTesterFixture::USERNAME,
            CaldavTesterFixture::SECRET,
        ]);

        $process->start();

        while ($process->isRunning()) {
            delay(0.01);
        }

        expect($process->isSuccessful())->toBeTrue()
            ->and($process->getOutput())->toContain('/dav/principals/');
    } finally {
        $server->stop();
    }
});

function freeIntegrationPort(): int
{
    $socket = socket_create_listen(0);
    if ($socket === false) {
        throw new RuntimeException('Could not allocate a free port: '.socket_strerror(socket_last_error()));
    }

    socket_getsockname($socket, $address, $port);
    socket_close($socket);

    return $port;
}
