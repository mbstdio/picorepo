<?php

namespace Tests\Feature;

use App\Models\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class ComposerInstallTest extends TestCase
{
    public function test_composer_installs_a_package_from_a_public_repository(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The ZIP extension is required to build the test package archive.');
        }

        $directory = sys_get_temp_dir().'/pico-repo-composer-'.bin2hex(random_bytes(8));
        $archiveDirectory = 'tests/'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        touch($directory.'/database.sqlite');

        $originalDatabase = config('database.connections.sqlite.database');
        $originalAppUrl = config('app.url');
        $server = null;

        try {
            $port = $this->availablePort();
            config([
                'app.url' => "http://127.0.0.1:{$port}",
                'database.connections.sqlite.database' => $directory.'/database.sqlite',
            ]);
            DB::purge('sqlite');
            Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);

            $repository = Repository::create(['name' => 'acme', 'type' => 'public']);
            $package = $repository->packages()->create(['name' => 'demo']);
            $archivePath = $archiveDirectory.'/demo.zip';
            $this->createPackageArchive(Storage::disk('local')->path($archivePath));
            $package->versions()->create([
                'version' => '1.0.0',
                'type' => 'library',
                'disk' => 'local',
                'zip_path' => $archivePath,
            ]);

            $server = $this->startServer($port, $directory.'/database.sqlite');
            $this->waitForServer($port);

            file_put_contents($directory.'/composer.json', json_encode([
                'repositories' => [
                    ['packagist.org' => false],
                    ['type' => 'composer', 'url' => "http://127.0.0.1:{$port}/composer/{$repository->slug}"],
                ],
                'require' => ['acme/demo' => '^1.0'],
            ], JSON_THROW_ON_ERROR));

            $composer = new Process(['composer', 'install', '--no-interaction', '--no-plugins', '--no-scripts'], $directory);
            $composer->setTimeout(30)->run();

            $this->assertTrue($composer->isSuccessful(), $composer->getErrorOutput());
            $this->assertFileExists($directory.'/vendor/acme/demo/composer.json');
        } finally {
            $server?->stop();
            config([
                'app.url' => $originalAppUrl,
                'database.connections.sqlite.database' => $originalDatabase,
            ]);
            DB::purge('sqlite');
            Storage::disk('local')->deleteDirectory($archiveDirectory);
            $this->deleteDirectory($directory);
        }
    }

    private function createPackageArchive(string $path): void
    {
        mkdir(dirname($path), 0777, true);
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE);
        $archive->addFromString('composer.json', json_encode([
            'name' => 'acme/demo',
            'version' => '1.0.0',
        ], JSON_THROW_ON_ERROR));
        $archive->close();
    }

    private function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr(strrchr($address, ':'), 1);
    }

    private function startServer(int $port, string $database): Process
    {
        $server = new Process([
            PHP_BINARY,
            '-S',
            "127.0.0.1:{$port}",
            '-t',
            public_path(),
            public_path('index.php'),
        ], base_path(), array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'APP_URL' => "http://127.0.0.1:{$port}",
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
        ]));
        $server->start();

        return $server;
    }

    private function waitForServer(int $port): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (@file_get_contents("http://127.0.0.1:{$port}")) {
                return;
            }

            usleep(100_000);
        }

        $this->fail('The local Composer repository server did not start.');
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
