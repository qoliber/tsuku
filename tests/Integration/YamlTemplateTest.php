<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class YamlTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateYamlConfiguration(): void
    {
        $template = 'app:
  name: {app.name}
  version: {app.version}
  debug: {app.debug}

database:
  host: {db.host}
  port: {db.port}
  name: {db.name}

services:
@for(services as service)
  - name: {service.name}
    enabled: {service.enabled}
    port: {service.port}
@end
';

        $data = [
            'app' => [
                'name' => 'MyApp',
                'version' => '1.0.0',
                'debug' => 'true',
            ],
            'db' => [
                'host' => 'localhost',
                'port' => '5432',
                'name' => 'mydb',
            ],
            'services' => [
                ['name' => 'api', 'enabled' => 'true', 'port' => '8080'],
                ['name' => 'worker', 'enabled' => 'false', 'port' => '8081'],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('app:', $result);
        $this->assertStringContainsString('name: MyApp', $result);
        $this->assertStringContainsString('version: 1.0.0', $result);
        $this->assertStringContainsString('database:', $result);
        $this->assertStringContainsString('host: localhost', $result);
        $this->assertStringContainsString('port: 5432', $result);
        $this->assertStringContainsString('services:', $result);
        $this->assertStringContainsString('- name: api', $result);
        $this->assertStringContainsString('enabled: true', $result);
    }

    public function testGenerateYamlWithConditionals(): void
    {
        $template = 'environment: {env}
features:
@if(features.cache)
  cache:
    enabled: true
    driver: {features.cache.driver}
@end
@if(features.queue)
  queue:
    enabled: true
    connection: {features.queue.connection}
@end
';

        $data = [
            'env' => 'production',
            'features' => [
                'cache' => [
                    'driver' => 'redis',
                ],
                'queue' => null,
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('environment: production', $result);
        $this->assertStringContainsString('cache:', $result);
        $this->assertStringContainsString('driver: redis', $result);
        $this->assertStringNotContainsString('queue:', $result);
    }

    public function testGenerateDockerComposeYaml(): void
    {
        $template = 'version: "{version}"

services:
@for(services as service)
  {service.name}:
    image: {service.image}
    @if(service.ports)ports:
@for(service.ports as port)
      - "{port}"
@end
    @end@if(service.environment)environment:
@for(service.environment as value, key)
      {key}: {value}
@end
    @end
@end';

        $data = [
            'version' => '3.8',
            'services' => [
                [
                    'name' => 'web',
                    'image' => 'nginx:latest',
                    'ports' => ['80:80', '443:443'],
                    'environment' => null,
                ],
                [
                    'name' => 'app',
                    'image' => 'php:8.2-fpm',
                    'ports' => null,
                    'environment' => ['APP_ENV' => 'production', 'APP_DEBUG' => 'false'],
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('version: "3.8"', $result);
        $this->assertStringContainsString('web:', $result);
        $this->assertStringContainsString('image: nginx:latest', $result);
        $this->assertStringContainsString('- "80:80"', $result);
        $this->assertStringContainsString('app:', $result);
        $this->assertStringContainsString('APP_ENV: production', $result);
    }
}
