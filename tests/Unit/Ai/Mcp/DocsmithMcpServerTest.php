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

    expect($tools)->toBeArray()
        ->and(array_is_list($tools))->toBeTrue()
        ->and($tools)->toHaveCount(4);

    $names = array_column($tools, 'name');
    expect($names)->toContain('read_source')
        ->toContain('capture_media')
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

it('frames HTTP responses with CRLF line endings', function (): void {
    $server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $reflection = new ReflectionMethod(DocsmithMcpServer::class, 'httpResponse');

    $payload = ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['ok' => true]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $framed = $reflection->invoke($server, $payload);

    expect($framed)->toBe(
        "HTTP/1.1 200 OK\r\n"
        . "Content-Type: application/json\r\n"
        . 'Content-Length: ' . strlen($body) . "\r\n"
        . "Connection: close\r\n\r\n"
        . $body,
    );
});

it('reads HTTP requests arriving in multiple chunks', function (): void {
    $server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $pair = stream_socket_pair(
        DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX,
        STREAM_SOCK_STREAM,
        DIRECTORY_SEPARATOR === '\\' ? STREAM_IPPROTO_IP : 0,
    );

    if ($pair === false) {
        throw new RuntimeException('stream_socket_pair failed');
    }

    $payload = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []], JSON_THROW_ON_ERROR);
    $headers = "POST / HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n\r\n";

    fwrite($pair[0], $headers . substr($payload, 0, 10));
    usleep(10000);
    fwrite($pair[0], substr($payload, 10));

    $reflection = new ReflectionMethod(DocsmithMcpServer::class, 'readHttpRequest');
    $read = $reflection->invoke($server, $pair[1]);

    expect($read)->toBe($headers . $payload);

    fclose($pair[0]);
    fclose($pair[1]);
});
