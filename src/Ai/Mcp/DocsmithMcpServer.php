<?php

declare(strict_types=1);

namespace Docsmith\Ai\Mcp;

use Docsmith\Ai\Tools\ReadSourceTool;
use Docsmith\Ai\Tools\WriteMarkdownTool;
use Docsmith\Docsmith;

final class DocsmithMcpServer
{
    private array $tools = [];

    private string $transport;

    private int $port;

    public function __construct(
        string $transport = 'stdio',
        int $port = 8090,
        string $sourcePath = '',
        string $docsSourcePath = '',
    ) {
        $this->transport = $transport;
        $this->port = $port;
        $this->registerTools($sourcePath, $docsSourcePath);
    }

    public function run(): void
    {
        if ($this->transport === 'http') {
            $this->runHttp();
        } else {
            $this->runStdio();
        }
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function handleRequest(array $request): array
    {
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        return match ($method) {
            'initialize' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'docsmith', 'version' => '1.0.0'],
            ]],
            'tools/list' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'tools' => array_map(fn ($t) => [
                    'name' => $t->name(),
                    'description' => $t->description(),
                    'inputSchema' => $t->inputSchema(),
                ], $this->tools),
            ]],
            'tools/call' => $this->handleToolCall($params),
            default => ['jsonrpc' => '2.0', 'id' => $id, 'error' => [
                'code' => -32601,
                'message' => "Method not found: {$method}",
            ]],
        };
    }

    private function registerTools(string $sourcePath, string $docsSourcePath): void
    {
        if ($sourcePath !== '') {
            $this->tools['read_source'] = new ReadSourceTool($sourcePath);
        }

        if ($docsSourcePath !== '') {
            $this->tools['write_markdown'] = new WriteMarkdownTool($docsSourcePath);
        }

        $this->tools['build_site'] = new class () implements \Docsmith\Ai\Tools\ToolInterface {
            public function name(): string
            {
                return 'build_site';
            }
            public function description(): string
            {
                return 'Build the static documentation site from markdown source.';
            }
            public function inputSchema(): array
            {
                return [
                    'source' => ['type' => 'string', 'description' => 'Docs source directory'],
                    'output' => ['type' => 'string', 'description' => 'Output directory'],
                    'title' => ['type' => 'string', 'description' => 'Site title'],
                ];
            }
            public function handle(array $input): array
            {
                Docsmith::make()
                    ->source($input['source'] ?? 'docs-source')
                    ->output($input['output'] ?? 'docs')
                    ->title($input['title'] ?? 'Documentation')
                    ->build();
                return ['success' => true];
            }
        };
    }

    private function handleToolCall(array $params): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        $id = $params['id'] ?? null;

        $tool = $this->tools[$name] ?? null;

        if ($tool === null) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32602, 'message' => "Unknown tool: {$name}"],
            ];
        }

        try {
            $result = $tool->handle($arguments);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => ['content' => [['type' => 'text', 'text' => json_encode($result)]]],
            ];
        } catch (\Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32603, 'message' => $e->getMessage()],
            ];
        }
    }

    private function runStdio(): void
    {
        while (true) {
            $line = fgets(STDIN);

            if ($line === false || $line === '') {
                break;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $request = json_decode($line, true);

            if ($request === null) {
                continue;
            }

            $response = $this->handleRequest($request);
            echo json_encode($response) . "\n";
            fflush(STDOUT);
        }
    }

    private function runHttp(): void
    {
        $address = "0.0.0.0:{$this->port}";
        $server = stream_socket_server("tcp://{$address}", $errno, $errstr);

        if ($server === false) {
            throw new \RuntimeException("Failed to start HTTP server: {$errstr} ({$errno})");
        }

        while ($conn = stream_socket_accept($server, -1)) {
            $data = fread($conn, 65536);
            $request = $this->parseHttpRequest($data);

            if ($request !== null) {
                $response = $this->handleRequest($request);
                $body = json_encode($response);
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n{$body}");
            }

            fclose($conn);
        }
    }

    private function parseHttpRequest(string $data): ?array
    {
        if (! preg_match('/\{.*\}/s', $data, $m)) {
            return null;
        }

        return json_decode($m[0], true);
    }
}
