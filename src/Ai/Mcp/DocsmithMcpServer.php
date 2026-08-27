<?php

declare(strict_types=1);

namespace Docsmith\Ai\Mcp;

use Docsmith\Ai\Tools\CaptureMediaTool;
use Docsmith\Ai\Tools\ReadSourceTool;
use Docsmith\Ai\Tools\ToolInterface;
use Docsmith\Ai\Tools\WriteMarkdownTool;
use Docsmith\Docsmith;
use RuntimeException;
use Throwable;

final class DocsmithMcpServer
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function __construct(
        private readonly string $transport = 'stdio',
        private readonly int $port = 8090,
        string $sourcePath = '',
        string $docsSourcePath = '',
    ) {
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

    /**
     * @return array<string, ToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * @param  array<int|string, mixed>  $request
     * @return array{jsonrpc: string, id: mixed, result?: array<string, mixed>, error?: array{code: int, message: string}}
     */
    public function handleRequest(array $request): array
    {
        $method = is_string($request['method'] ?? null) ? $request['method'] : '';
        $params = is_array($request['params'] ?? null) ? $request['params'] : [];
        $id = $request['id'] ?? null;

        return match ($method) {
            'initialize' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'docsmith', 'version' => '1.0.0'],
            ]],
            'tools/list' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'tools' => array_values(array_map(fn (ToolInterface $t): array => [
                    'name' => $t->name(),
                    'description' => $t->description(),
                    'inputSchema' => $t->inputSchema(),
                ], $this->tools)),
            ]],
            'tools/call' => $this->handleToolCall($params, $id),
            default => ['jsonrpc' => '2.0', 'id' => $id, 'error' => [
                'code' => -32601,
                'message' => 'Method not found: ' . $method,
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
            $this->tools['capture_media'] = new CaptureMediaTool(
                $docsSourcePath,
                $sourcePath !== '' ? $sourcePath : (string) getcwd()
            );
        }

        $this->tools['build_site'] = new class () implements ToolInterface {
            public function name(): string
            {
                return 'build_site';
            }

            public function description(): string
            {
                return 'Build the static documentation site from markdown source.';
            }

            /**
             * @return array<string, mixed>
             */
            public function inputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'source' => ['type' => 'string', 'description' => 'Docs source directory'],
                        'output' => ['type' => 'string', 'description' => 'Output directory'],
                        'title' => ['type' => 'string', 'description' => 'Site title'],
                    ],
                ];
            }

            /**
             * @param  array<string, mixed>  $input
             * @return array<string, mixed>
             */
            public function handle(array $input): array
            {
                Docsmith::make()
                    ->source(is_string($input['source'] ?? null) ? $input['source'] : 'docs-source')
                    ->output(is_string($input['output'] ?? null) ? $input['output'] : 'docs')
                    ->title(is_string($input['title'] ?? null) ? $input['title'] : 'Documentation')
                    ->build();

                return ['success' => true];
            }
        };
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array{jsonrpc: string, id: mixed, result?: array<string, mixed>, error?: array{code: int, message: string}}
     */
    private function handleToolCall(array $params, mixed $id): array
    {
        $name = is_string($params['name'] ?? null) ? $params['name'] : '';
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $tool = $this->tools[$name] ?? null;

        if ($tool === null) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32602, 'message' => 'Unknown tool: ' . $name],
            ];
        }

        try {
            $result = $tool->handle($arguments);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => ['content' => [['type' => 'text', 'text' => json_encode($result)]]],
            ];
        } catch (Throwable $throwable) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32603, 'message' => $throwable->getMessage()],
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

            if (! is_array($request)) {
                continue;
            }

            $response = $this->handleRequest($request);
            echo json_encode($response) . "\n";
            fflush(STDOUT);
        }
    }

    private function runHttp(): void
    {
        $address = '127.0.0.1:' . $this->port;
        $server = stream_socket_server('tcp://' . $address, $errno, $errstr);

        if ($server === false) {
            throw new RuntimeException(sprintf('Failed to start HTTP server: %s (%s)', $errstr, $errno));
        }

        while ($conn = stream_socket_accept($server, -1)) {
            stream_set_blocking($conn, true);
            $this->handleHttpRequest($conn);
        }
    }

    /**
     * @param  resource  $conn
     */
    private function handleHttpRequest($conn): void
    {
        $data = $this->readHttpRequest($conn);

        $request = $this->parseHttpRequest($data);

        if ($request !== null) {
            $response = $this->httpResponse($this->handleRequest($request));
            $written = 0;
            $length = strlen($response);

            while ($written < $length) {
                $chunk = fwrite($conn, substr($response, $written));

                if ($chunk === false || $chunk === 0) {
                    break;
                }

                $written += $chunk;
            }
        }

        fclose($conn);
    }

    /**
     * @param  resource  $conn
     */
    private function readHttpRequest($conn): string
    {
        stream_set_timeout($conn, 10);
        $maxBytes = 1024 * 1024;
        $deadline = time() + 30;
        $data = '';

        while (time() < $deadline) {
            $remaining = $maxBytes - strlen($data);
            if ($remaining <= 0) {
                break;
            }

            $chunk = fread($conn, min(8192, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }

            $data .= $chunk;

            $headerEnd = strpos($data, "\r\n\r\n");
            if ($headerEnd === false) {
                continue;
            }

            if (preg_match('/Content-Length:\s*(\d+)/i', $data, $m)) {
                if (strlen($data) - $headerEnd - 4 >= (int) $m[1]) {
                    break;
                }

                continue;
            }

            break;
        }

        return $data;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function parseHttpRequest(string $data): ?array
    {
        if (! preg_match('/\{.*\}/s', $data, $m)) {
            return null;
        }

        $decoded = json_decode($m[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int|string, mixed>  $response
     */
    private function httpResponse(array $response): string
    {
        $body = json_encode($response) ?: '';

        return "HTTP/1.1 200 OK\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $body;
    }
}
