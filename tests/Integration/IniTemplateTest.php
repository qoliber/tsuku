<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class IniTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateIniConfiguration(): void
    {
        $template = '; Application Configuration File
; Generated on {date}

[application]
name = {app.name}
version = {app.version}
debug = {app.debug}

[database]
host = {db.host}
port = {db.port}
database = {db.name}
username = {db.user}
password = {db.password}

[cache]
driver = {cache.driver}
ttl = {cache.ttl}
';

        $data = [
            'date' => '2024-01-15',
            'app' => [
                'name' => 'MyApp',
                'version' => '1.0.0',
                'debug' => 'false',
            ],
            'db' => [
                'host' => 'localhost',
                'port' => '5432',
                'name' => 'mydb',
                'user' => 'admin',
                'password' => 'secret',
            ],
            'cache' => [
                'driver' => 'redis',
                'ttl' => '3600',
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('; Generated on 2024-01-15', $result);
        $this->assertStringContainsString('[application]', $result);
        $this->assertStringContainsString('name = MyApp', $result);
        $this->assertStringContainsString('version = 1.0.0', $result);
        $this->assertStringContainsString('[database]', $result);
        $this->assertStringContainsString('host = localhost', $result);
        $this->assertStringContainsString('port = 5432', $result);
        $this->assertStringContainsString('[cache]', $result);
        $this->assertStringContainsString('driver = redis', $result);
    }

    public function testGeneratePhpIni(): void
    {
        $template = '; PHP Configuration

[PHP]
@for(php as value, key)
{key} = {value}
@end

[Date]
@for(date as value, key)
{key} = {value}
@end
';

        $data = [
            'php' => [
                'max_execution_time' => '30',
                'memory_limit' => '128M',
                'display_errors' => 'Off',
                'error_reporting' => 'E_ALL',
            ],
            'date' => [
                'date.timezone' => 'UTC',
                'date.default_latitude' => '31.7667',
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('[PHP]', $result);
        $this->assertStringContainsString('max_execution_time = 30', $result);
        $this->assertStringContainsString('memory_limit = 128M', $result);
        $this->assertStringContainsString('[Date]', $result);
        $this->assertStringContainsString('date.timezone = UTC', $result);
    }

    public function testGenerateIniWithConditionals(): void
    {
        $template = '[general]
app_name = {app.name}
environment = {env}

@if(features.logging)
[logging]
enabled = true
level = {features.logging.level}
path = {features.logging.path}
@end

@if(features.mail)
[mail]
driver = {features.mail.driver}
host = {features.mail.host}
port = {features.mail.port}
@end

@unless(features.debug)
[production]
optimize = true
cache = true
@end
';

        $data = [
            'app' => ['name' => 'MyApp'],
            'env' => 'production',
            'features' => [
                'logging' => [
                    'level' => 'error',
                    'path' => '/var/log/app.log',
                ],
                'mail' => null,
                'debug' => false,
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('[general]', $result);
        $this->assertStringContainsString('environment = production', $result);
        $this->assertStringContainsString('[logging]', $result);
        $this->assertStringContainsString('level = error', $result);
        $this->assertStringNotContainsString('[mail]', $result);
        $this->assertStringContainsString('[production]', $result);
        $this->assertStringContainsString('optimize = true', $result);
    }

    public function testGenerateIniWithArrays(): void
    {
        $template = '[servers]
@for(servers as server, index)
server[{index}][host] = {server.host}
server[{index}][port] = {server.port}
@end

[allowed_hosts]
@for(hosts as host, index)
hosts[] = {host}
@end
';

        $data = [
            'servers' => [
                ['host' => '192.168.1.10', 'port' => '80'],
                ['host' => '192.168.1.11', 'port' => '8080'],
            ],
            'hosts' => [
                'example.com',
                'test.com',
                'localhost',
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('[servers]', $result);
        $this->assertStringContainsString('server[0][host] = 192.168.1.10', $result);
        $this->assertStringContainsString('server[0][port] = 80', $result);
        $this->assertStringContainsString('server[1][host] = 192.168.1.11', $result);
        $this->assertStringContainsString('[allowed_hosts]', $result);
        $this->assertStringContainsString('hosts[] = example.com', $result);
        $this->assertStringContainsString('hosts[] = test.com', $result);
    }

    public function testGenerateApacheIni(): void
    {
        $template = '; Apache Virtual Host Configuration

[vhost:{vhost.domain}]
ServerName = {vhost.domain}
DocumentRoot = {vhost.docroot}
@if(vhost.ssl)
SSLEngine = On
SSLCertificateFile = {vhost.ssl.cert}
SSLCertificateKeyFile = {vhost.ssl.key}
@end

@for(vhost.directories as dir)
[Directory:{dir.path}]
AllowOverride = {dir.override}
Require = {dir.require}
@end
';

        $data = [
            'vhost' => [
                'domain' => 'example.com',
                'docroot' => '/var/www/example',
                'ssl' => [
                    'cert' => '/etc/ssl/cert.pem',
                    'key' => '/etc/ssl/key.pem',
                ],
                'directories' => [
                    [
                        'path' => '/var/www/example',
                        'override' => 'All',
                        'require' => 'all granted',
                    ],
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('[vhost:example.com]', $result);
        $this->assertStringContainsString('ServerName = example.com', $result);
        $this->assertStringContainsString('SSLEngine = On', $result);
        $this->assertStringContainsString('SSLCertificateFile = /etc/ssl/cert.pem', $result);
        $this->assertStringContainsString('[Directory:/var/www/example]', $result);
        $this->assertStringContainsString('AllowOverride = All', $result);
    }
}
