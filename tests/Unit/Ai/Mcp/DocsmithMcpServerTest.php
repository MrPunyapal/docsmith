<?php

declare(strict_types=1);

use Docsmith\Ai\Mcp\DocsmithMcpServer;

it('responds to initialize request', function (): void {
    $server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ]);

    expect($response)->toHaveKey('result');

    $result = $response['result'] ?? [];
    expect($result)->toHaveKey('protocolVersion')
        ->and($result)->toHaveKey('serverInfo');

    $serverInfo = $result['serverInfo'] ?? [];
    $serverInfo = is_array($serverInfo) ? $serverInfo : [];

    expect($serverInfo['name'] ?? null)->toBe('docsmith');
});

it('returns tool list', function (): void {
    $server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ]);

    expect($response)->toHaveKey('result');

    $result = $response['result'] ?? [];
    expect($result)->toHaveKey('tools');

    $tools = $result['tools'] ?? [];
    $tools = is_array($tools) ? $tools : [];

    $names = array_column($tools, 'name');
    expect($names)->toContain('read_source')
        ->toContain('build_site');

    foreach (array_column($tools, 'inputSchema') as $schema) {
        expect($schema)->toHaveKey('type', 'object')
            ->and($schema)->toHaveKey('properties');
    }
});

it('calls read_source tool', function (): void {
    $server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'read_source',
            'arguments' => [
                'action' => 'list_files',
                'pattern' => '**/*.php',
            ],
        ],
    ]);

    expect($response)->toHaveKey('result');

    $result = $response['result'] ?? [];
    expect($result)->toHaveKey('content');

    $content = $result['content'] ?? [];
    $content = is_array($content) ? $content : [];
    expect($content[0] ?? null)->toHaveKey('text')
        ->and($response['id'])->toBe(1);
});

it('returns error for unknown tool', function (): void {
    $server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'nonexistent_tool',
            'arguments' => [],
        ],
    ]);

    $error = $response['error'] ?? [];
    expect($error)->toHaveKey('message')
        ->and($error['message'] ?? null)->toContain('Unknown tool');
});

it('returns error for unsupported method', function (): void {
    $server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'unknown_method',
        'params' => [],
    ]);

    $error = $response['error'] ?? [];
    expect($error)->toHaveKey('message')
        ->and($error['message'] ?? null)->toContain('Method not found');
});
