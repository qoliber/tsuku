<?php

/**
 * YAML Export Examples
 *
 * Demonstrates YAML generation for configuration files,
 * Docker Compose, Kubernetes, CI/CD pipelines, and more.
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

echo "=== YAML EXPORT EXAMPLES ===\n\n";

// Example 1: Application Configuration
echo "1. Application Configuration (YAML)\n";
echo str_repeat('-', 60) . "\n";

$template1 = '# Application Configuration
# Generated on @date("Y-m-d H:i:s")

app:
  name: {app.name}
  version: {app.version}
  environment: {app.environment}
  debug: @?{app.debug "true" : "false"}

database:
  host: {database.host}
  port: {database.port}
  name: {database.name}
  username: {database.username}
  password: {database.password}

cache:
  driver: {cache.driver}
  ttl: {cache.ttl}
@if(cache.redis)
  redis:
    host: {cache.redis.host}
    port: {cache.redis.port}
@end

services:
@for(services as service)
  {service.name}:
    enabled: @?{service.enabled "true" : "false"}
    endpoint: {service.endpoint}
@if(service.credentials)
    credentials:
      api_key: {service.credentials.api_key}
@end
@end';

$data1 = [
    'app' => [
        'name' => 'MyApp',
        'version' => '2.0.0',
        'environment' => 'production',
        'debug' => false
    ],
    'database' => [
        'host' => 'db.example.com',
        'port' => 3306,
        'name' => 'myapp_prod',
        'username' => 'dbuser',
        'password' => 'secret123'
    ],
    'cache' => [
        'driver' => 'redis',
        'ttl' => 3600,
        'redis' => [
            'host' => 'redis.example.com',
            'port' => 6379
        ]
    ],
    'services' => [
        [
            'name' => 'payment',
            'enabled' => true,
            'endpoint' => 'https://api.payment.com',
            'credentials' => ['api_key' => 'pk_live_123456']
        ],
        [
            'name' => 'email',
            'enabled' => true,
            'endpoint' => 'https://api.email.com',
            'credentials' => ['api_key' => 'key_789']
        ],
    ]
];

echo $tsuku->process($template1, $data1);
echo "\n\n";

// Example 2: Docker Compose
echo "2. Docker Compose (docker-compose.yml)\n";
echo str_repeat('-', 60) . "\n";

$template2 = 'version: \'{compose.version}\'

services:
@for(compose.services as service)
  {service.name}:
    image: {service.image}
@if(service.build)
    build:
      context: {service.build.context}
      dockerfile: {service.build.dockerfile}
@end
@if(service.ports)
    ports:
      @for(service.ports as port)
      - "{port}"
      @end
@end
@if(service.environment)
    environment:
      @for(service.environment as value, key)
      {key}: {value}
      @end
@end
@if(service.volumes)
    volumes:
      @for(service.volumes as volume)
      - {volume}
      @end
@end
@if(service.depends_on)
    depends_on:
      @for(service.depends_on as dep)
      - {dep}
      @end
@end
@end

@if(compose.networks)
networks:
@for(compose.networks as network)
  {network.name}:
    driver: {network.driver}
@end
@end';

$data2 = [
    'compose' => [
        'version' => '3.8',
        'services' => [
            [
                'name' => 'web',
                'image' => 'nginx:alpine',
                'ports' => ['80:80', '443:443'],
                'volumes' => ['./html:/usr/share/nginx/html:ro'],
                'depends_on' => ['app'],
                'environment' => []
            ],
            [
                'name' => 'app',
                'build' => [
                    'context' => '.',
                    'dockerfile' => 'Dockerfile'
                ],
                'environment' => [
                    'APP_ENV' => 'production',
                    'DB_HOST' => 'database',
                    'DB_PORT' => '3306'
                ],
                'depends_on' => ['database'],
                'ports' => [],
                'volumes' => ['./app:/var/www/html']
            ],
            [
                'name' => 'database',
                'image' => 'mysql:8.0',
                'environment' => [
                    'MYSQL_ROOT_PASSWORD' => 'root',
                    'MYSQL_DATABASE' => 'myapp'
                ],
                'volumes' => ['db_data:/var/lib/mysql'],
                'ports' => [],
                'depends_on' => []
            ],
        ],
        'networks' => [
            ['name' => 'app_network', 'driver' => 'bridge']
        ]
    ]
];

echo $tsuku->process($template2, $data2);
echo "\n\n";

// Example 3: Kubernetes Deployment
echo "3. Kubernetes Deployment (deployment.yaml)\n";
echo str_repeat('-', 60) . "\n";

$template3 = 'apiVersion: apps/v1
kind: Deployment
metadata:
  name: {deployment.name}
  namespace: {deployment.namespace}
  labels:
@for(deployment.labels as value, key)
    {key}: {value}
@end
spec:
  replicas: {deployment.replicas}
  selector:
    matchLabels:
@for(deployment.selector as value, key)
      {key}: {value}
@end
  template:
    metadata:
      labels:
@for(deployment.selector as value, key)
        {key}: {value}
@end
    spec:
      containers:
@for(deployment.containers as container)
      - name: {container.name}
        image: {container.image}
@if(container.ports)
        ports:
@for(container.ports as port)
        - containerPort: {port.containerPort}
          protocol: {port.protocol}
@end
@end
@if(container.env)
        env:
@for(container.env as env)
        - name: {env.name}
          value: "{env.value}"
@end
@end
@if(container.resources)
        resources:
          requests:
            memory: "{container.resources.requests.memory}"
            cpu: "{container.resources.requests.cpu}"
          limits:
            memory: "{container.resources.limits.memory}"
            cpu: "{container.resources.limits.cpu}"
@end
@end';

$data3 = [
    'deployment' => [
        'name' => 'myapp-deployment',
        'namespace' => 'production',
        'labels' => [
            'app' => 'myapp',
            'version' => 'v1.0.0'
        ],
        'replicas' => 3,
        'selector' => [
            'app' => 'myapp'
        ],
        'containers' => [
            [
                'name' => 'myapp',
                'image' => 'myapp:1.0.0',
                'ports' => [
                    ['containerPort' => 8080, 'protocol' => 'TCP']
                ],
                'env' => [
                    ['name' => 'APP_ENV', 'value' => 'production'],
                    ['name' => 'DB_HOST', 'value' => 'mysql.production.svc.cluster.local'],
                ],
                'resources' => [
                    'requests' => ['memory' => '256Mi', 'cpu' => '250m'],
                    'limits' => ['memory' => '512Mi', 'cpu' => '500m']
                ]
            ]
        ]
    ]
];

echo $tsuku->process($template3, $data3);
echo "\n\n";

// Example 4: GitHub Actions Workflow
echo "4. GitHub Actions CI/CD Workflow\n";
echo str_repeat('-', 60) . "\n";

$template4 = 'name: {workflow.name}

on:
@for(workflow.on as trigger)
  {trigger}:
@end

jobs:
@for(workflow.jobs as job)
  {job.name}:
    runs-on: {job.runs_on}
@if(job.steps)
    steps:
@for(job.steps as step)
      - name: {step.name}
@if(step.uses)
        uses: {step.uses}
@end
@if(step.run)
        run: |
          {step.run}
@end
@if(step.with)
        with:
@for(step.with as value, key)
          {key}: {value}
@end
@end
@end
@end
@end';

$data4 = [
    'workflow' => [
        'name' => 'CI/CD Pipeline',
        'on' => ['push', 'pull_request'],
        'jobs' => [
            [
                'name' => 'test',
                'runs_on' => 'ubuntu-latest',
                'steps' => [
                    [
                        'name' => 'Checkout code',
                        'uses' => 'actions/checkout@v3',
                        'with' => [],
                        'run' => null
                    ],
                    [
                        'name' => 'Setup PHP',
                        'uses' => 'shivammathur/setup-php@v2',
                        'with' => [
                            'php-version' => '8.1'
                        ],
                        'run' => null
                    ],
                    [
                        'name' => 'Install dependencies',
                        'uses' => null,
                        'run' => 'composer install --no-interaction --prefer-dist',
                        'with' => []
                    ],
                    [
                        'name' => 'Run tests',
                        'uses' => null,
                        'run' => 'composer test',
                        'with' => []
                    ],
                ]
            ],
            [
                'name' => 'deploy',
                'runs_on' => 'ubuntu-latest',
                'steps' => [
                    [
                        'name' => 'Deploy to production',
                        'uses' => null,
                        'run' => 'echo "Deploying to production..."',
                        'with' => []
                    ],
                ]
            ],
        ]
    ]
];

echo $tsuku->process($template4, $data4);
echo "\n\n";

// Example 5: OpenAPI/Swagger YAML
echo "5. OpenAPI Specification (Simplified)\n";
echo str_repeat('-', 60) . "\n";

$template5 = 'openapi: {api.version}
info:
  title: {api.info.title}
  description: {api.info.description}
  version: {api.info.api_version}

servers:
@for(api.servers as server)
  - url: {server.url}
    description: {server.description}
@end

paths:
@for(api.paths as path)
  {path.endpoint}:
@for(path.methods as method)
    {method.type}:
      summary: {method.summary}
      description: {method.description}
@if(method.parameters)
      parameters:
@for(method.parameters as param)
        - name: {param.name}
          in: {param.in}
          required: @?{param.required "true" : "false"}
          schema:
            type: {param.type}
@end
@end
      responses:
@for(method.responses as response)
        \'{response.code}\':
          description: {response.description}
@end
@end
@end';

$data5 = [
    'api' => [
        'version' => '3.0.0',
        'info' => [
            'title' => 'My API',
            'description' => 'RESTful API for MyApp',
            'api_version' => '1.0.0'
        ],
        'servers' => [
            ['url' => 'https://api.example.com/v1', 'description' => 'Production'],
            ['url' => 'https://staging-api.example.com/v1', 'description' => 'Staging']
        ],
        'paths' => [
            [
                'endpoint' => '/products',
                'methods' => [
                    [
                        'type' => 'get',
                        'summary' => 'Get all products',
                        'description' => 'Returns a list of all products',
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'required' => false, 'type' => 'integer'],
                            ['name' => 'limit', 'in' => 'query', 'required' => false, 'type' => 'integer']
                        ],
                        'responses' => [
                            ['code' => '200', 'description' => 'Successful response'],
                            ['code' => '400', 'description' => 'Bad request']
                        ]
                    ]
                ]
            ],
            [
                'endpoint' => '/products/{id}',
                'methods' => [
                    [
                        'type' => 'get',
                        'summary' => 'Get product by ID',
                        'description' => 'Returns a single product',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'type' => 'string']
                        ],
                        'responses' => [
                            ['code' => '200', 'description' => 'Successful response'],
                            ['code' => '404', 'description' => 'Product not found']
                        ]
                    ]
                ]
            ]
        ]
    ]
];

echo $tsuku->process($template5, $data5);
echo "\n\n";

echo "=== All YAML examples completed! ===\n";
