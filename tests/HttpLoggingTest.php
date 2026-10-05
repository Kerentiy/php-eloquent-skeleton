<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Runs public/index.php in a separate PHP process (so shutdown functions fire)
 * and checks that the request and the response body end up in http-*.log.
 */
final class HttpLoggingTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/http-log-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testRequestAndResponseBodyAreLogged(): void
    {
        $script = sprintf(
            '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/missing"; $_SERVER["REMOTE_ADDR"]="127.0.0.1";'
            . ' require %s;',
            var_export(dirname(__DIR__) . '/public/index.php', true),
        );

        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [
                'LOG_PATH' => $this->dir,
                'LOG_LEVEL' => 'debug',
                'DB_DATABASE' => ':memory:',
                'PATH' => getenv('PATH') ?: '',
            ],
        );
        $stdout = stream_get_contents($pipes[1]);
        proc_close($process);

        $this->assertStringContainsString('"error": "Not found"', $stdout);

        $files = glob($this->dir . '/http-*.log') ?: [];
        $this->assertCount(1, $files);

        $log = (string) file_get_contents($files[0]);
        $this->assertStringContainsString('http.WARNING', $log);
        $this->assertStringContainsString('GET /missing -> 404', $log);
        $this->assertStringContainsString('Not found', $log, 'response body must be logged');
    }
}
