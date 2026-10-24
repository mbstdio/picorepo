<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

if (is_file(dirname(__DIR__, 2).'/public/hot')) {
    throw new RuntimeException('Stop Vite and remove its public/hot file to test production assets.');
}

// Only this disposable database is migrated; never use the developer's database.
$database = tempnam(sys_get_temp_dir(), 'picorepo-browser-');
foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
    'APP_URL' => 'http://127.0.0.1:8010',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'SESSION_DRIVER' => 'file',
    'CACHE_STORE' => 'array',
    'BCRYPT_ROUNDS' => '4',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Clear cached configuration before running browser tests.');
}
Artisan::call('migrate', ['--force' => true]);
User::factory()->create([
    'name' => str_repeat('Long account name ', 8),
    'email' => str_repeat('long', 12).'@example.test',
]);
$kernel->call('serve', ['--host' => '127.0.0.1', '--port' => '8010', '--tries' => 1, '--no-reload' => true]);
