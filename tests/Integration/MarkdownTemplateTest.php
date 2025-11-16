<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class MarkdownTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateMarkdownDocument(): void
    {
        $template = '# {title}

{description}

## Installation

```bash
{installation.command}
```

## Features

@for(features as feature)
- {feature}
@end

## License

{license}
';

        $data = [
            'title' => 'Awesome Project',
            'description' => 'A really cool project that does amazing things.',
            'installation' => [
                'command' => 'composer require vendor/package',
            ],
            'features' => [
                'Fast and efficient',
                'Easy to use',
                'Well documented',
                'Fully tested',
            ],
            'license' => 'MIT',
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('# Awesome Project', $result);
        $this->assertStringContainsString('A really cool project', $result);
        $this->assertStringContainsString('## Installation', $result);
        $this->assertStringContainsString('composer require vendor/package', $result);
        $this->assertStringContainsString('## Features', $result);
        $this->assertStringContainsString('- Fast and efficient', $result);
        $this->assertStringContainsString('- Easy to use', $result);
        $this->assertStringContainsString('MIT', $result);
    }

    public function testGenerateMarkdownTable(): void
    {
        $template = '# API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
@for(endpoints as endpoint)
| {endpoint.method} | `{endpoint.path}` | {endpoint.description} |
@end
';

        $data = [
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/users', 'description' => 'List all users'],
                ['method' => 'POST', 'path' => '/api/users', 'description' => 'Create a user'],
                ['method' => 'GET', 'path' => '/api/users/:id', 'description' => 'Get user by ID'],
                ['method' => 'DELETE', 'path' => '/api/users/:id', 'description' => 'Delete a user'],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('# API Endpoints', $result);
        $this->assertStringContainsString('| Method | Endpoint | Description |', $result);
        $this->assertStringContainsString('| GET | `/api/users` | List all users |', $result);
        $this->assertStringContainsString('| POST | `/api/users` | Create a user |', $result);
        $this->assertStringContainsString('| DELETE | `/api/users/:id` | Delete a user |', $result);
    }

    public function testGenerateMarkdownWithConditionals(): void
    {
        $template = '# {package.name}

{package.description}

@if(package.deprecated)
> **Warning**: This package is deprecated. Please use [{package.replacement}]({package.replacementUrl}) instead.
@end

## Installation

```bash
{installation}
```

@if(badges.build)
![Build Status]({badges.build})
@end
@if(badges.coverage)
![Coverage]({badges.coverage})
@end

@unless(contributing)
## Contributing

Contributions are welcome!
@end
';

        $data = [
            'package' => [
                'name' => 'old-package',
                'description' => 'An outdated package.',
                'deprecated' => true,
                'replacement' => 'new-package',
                'replacementUrl' => 'https://github.com/vendor/new-package',
            ],
            'installation' => 'composer require vendor/old-package',
            'badges' => [
                'build' => 'https://travis-ci.org/vendor/package.svg',
                'coverage' => null,
            ],
            'contributing' => false,
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('# old-package', $result);
        $this->assertStringContainsString('> **Warning**: This package is deprecated', $result);
        $this->assertStringContainsString('[new-package](https://github.com/vendor/new-package)', $result);
        $this->assertStringContainsString('![Build Status](https://travis-ci.org/vendor/package.svg)', $result);
        $this->assertStringNotContainsString('![Coverage]', $result);
        $this->assertStringContainsString('## Contributing', $result);
    }

    public function testGenerateMarkdownChangelog(): void
    {
        $template = '# Changelog

All notable changes to this project will be documented in this file.

@for(versions as version)
## [{version.number}] - {version.date}

@if(version.added)
### Added
@for(version.added as item)
- {item}
@end

@end
@if(version.changed)
### Changed
@for(version.changed as item)
- {item}
@end

@end
@if(version.fixed)
### Fixed
@for(version.fixed as item)
- {item}
@end

@end
@end
';

        $data = [
            'versions' => [
                [
                    'number' => '2.0.0',
                    'date' => '2024-01-15',
                    'added' => [
                        'New feature X',
                        'Support for Y',
                    ],
                    'changed' => [
                        'Updated API endpoints',
                    ],
                    'fixed' => null,
                ],
                [
                    'number' => '1.0.1',
                    'date' => '2023-12-01',
                    'added' => null,
                    'changed' => null,
                    'fixed' => [
                        'Fixed bug in parser',
                        'Corrected typo in docs',
                    ],
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('# Changelog', $result);
        $this->assertStringContainsString('## [2.0.0] - 2024-01-15', $result);
        $this->assertStringContainsString('### Added', $result);
        $this->assertStringContainsString('- New feature X', $result);
        $this->assertStringContainsString('### Changed', $result);
        $this->assertStringContainsString('- Updated API endpoints', $result);
        $this->assertStringContainsString('## [1.0.1] - 2023-12-01', $result);
        $this->assertStringContainsString('### Fixed', $result);
        $this->assertStringContainsString('- Fixed bug in parser', $result);
    }

    public function testGenerateMarkdownWithCodeBlocks(): void
    {
        $template = '# Usage Examples

@for(examples as example)
## {example.title}

{example.description}

```{example.language}
{example.code}
```

@end
';

        $data = [
            'examples' => [
                [
                    'title' => 'Basic Usage',
                    'description' => 'Here is a simple example:',
                    'language' => 'php',
                    'code' => '$tsuku = new Tsuku();
$result = $tsuku->process($template, $data);',
                ],
                [
                    'title' => 'Advanced Example',
                    'description' => 'With custom functions:',
                    'language' => 'php',
                    'code' => '$tsuku->registerFunction("custom", fn($x) => $x * 2);',
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('# Usage Examples', $result);
        $this->assertStringContainsString('## Basic Usage', $result);
        $this->assertStringContainsString('```php', $result);
        $this->assertStringContainsString('$tsuku = new Tsuku();', $result);
        $this->assertStringContainsString('## Advanced Example', $result);
        $this->assertStringContainsString('$tsuku->registerFunction', $result);
    }
}
