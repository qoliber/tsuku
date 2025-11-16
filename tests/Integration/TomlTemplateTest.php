<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class TomlTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateTomlConfiguration(): void
    {
        $template = '# Application Configuration
[app]
name = "{app.name}"
version = "{app.version}"
debug = {app.debug}

[database]
host = "{db.host}"
port = {db.port}
database = "{db.name}"
username = "{db.user}"

@for(servers as server)
[[servers]]
name = "{server.name}"
host = "{server.host}"
port = {server.port}
@end
';

        $data = [
            'app' => [
                'name' => 'MyApp',
                'version' => '1.0.0',
                'debug' => 'false',
            ],
            'db' => [
                'host' => 'localhost',
                'port' => 5432,
                'name' => 'mydb',
                'user' => 'admin',
            ],
            'servers' => [
                ['name' => 'web', 'host' => '192.168.1.10', 'port' => 80],
                ['name' => 'api', 'host' => '192.168.1.11', 'port' => 8080],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('[app]', $result);
        $this->assertStringContainsString('name = "MyApp"', $result);
        $this->assertStringContainsString('version = "1.0.0"', $result);
        $this->assertStringContainsString('[database]', $result);
        $this->assertStringContainsString('host = "localhost"', $result);
        $this->assertStringContainsString('port = 5432', $result);
        $this->assertStringContainsString('[[servers]]', $result);
        $this->assertStringContainsString('name = "web"', $result);
        $this->assertStringContainsString('host = "192.168.1.10"', $result);
    }

    public function testGenerateTomlWithConditionals(): void
    {
        $template = '[server]
host = "{host}"
port = {port}

@if(ssl.enabled)
[ssl]
enabled = true
cert = "{ssl.cert}"
key = "{ssl.key}"
@end

@unless(cache.disabled)
[cache]
driver = "{cache.driver}"
ttl = {cache.ttl}
@end
';

        $data = [
            'host' => 'example.com',
            'port' => 443,
            'ssl' => [
                'enabled' => true,
                'cert' => '/path/to/cert.pem',
                'key' => '/path/to/key.pem',
            ],
            'cache' => [
                'disabled' => false,
                'driver' => 'redis',
                'ttl' => 3600,
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('[server]', $result);
        $this->assertStringContainsString('host = "example.com"', $result);
        $this->assertStringContainsString('[ssl]', $result);
        $this->assertStringContainsString('enabled = true', $result);
        $this->assertStringContainsString('[cache]', $result);
        $this->assertStringContainsString('driver = "redis"', $result);
    }

    public function testGenerateCargoToml(): void
    {
        $template = '[package]
name = "{package.name}"
version = "{package.version}"
edition = "{package.edition}"

@for(dependencies as version, name)
[dependencies]
{name} = "{version}"
@end
';

        $data = [
            'package' => [
                'name' => 'my-project',
                'version' => '0.1.0',
                'edition' => '2021',
            ],
            'dependencies' => [
                'serde' => '1.0',
                'tokio' => '1.28',
                'axum' => '0.6',
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('[package]', $result);
        $this->assertStringContainsString('name = "my-project"', $result);
        $this->assertStringContainsString('[dependencies]', $result);
        $this->assertStringContainsString('serde = "1.0"', $result);
        $this->assertStringContainsString('tokio = "1.28"', $result);
    }
}
