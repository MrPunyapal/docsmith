<?php

declare(strict_types=1);

use Docsmith\Ai\Mcp\DocsmithMcpServer;

beforeEach(function (): void {
    $this->server = new DocsmithMcpServer(
        transport: 'stdio',
        port: 8090,
        sourcePath: __DIR__ . '/../../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-mcp-' . uniqid(),
    );

    $this->docsDir = sys_get_temp_dir() . '/docsmith-mcp-' . uniqid();
    mkdir($this->docsDir, 0777, true);
});

it('responds to initialize request', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ]);

    expect($response)->toHaveKey('result')
        ->and($response['result'])->toHaveKey('protocolVersion')
        ->and($response['result'])->toHaveKey('serverInfo')
        ->and($response['result']['serverInfo']['name'])->toBe('docsmith');
});

it('returns tool list', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ]);

    expect($response)->toHaveKey('result')
        ->and($response['result'])->toHaveKey('tools');

    $tools = $response['result']['tools'];
    $names = array_column($tools, 'name');
    expect($names)->toContain('read_source')
        ->toContain('build_site');
});

it('calls read_source tool', function (): void {
    $response = $this->server->handleRequest([
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

    expect($response)->toHaveKey('result')
        ->and($response['result'])->toHaveKey('content')
        ->and($response['result']['content'][0])->toHaveKey('text');
});

it('returns error for unknown tool', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'nonexistent_tool',
            'arguments' => [],
        ],
    ]);

    expect($response)->toHaveKey('error')
        ->and($response['error']['message'])->toContain('Unknown tool');
});

it('returns error for unsupported method', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'unknown_method',
        'params' => [],
    ]);

    expect($response)->toHaveKey('error')
        ->and($response['error']['message'])->toContain('Method not found');
});
