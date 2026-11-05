<?php

namespace Nobuhiko\EccubeCloudLogging\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Nobuhiko\EccubeCloudLogging\Processor\PiiProcessor;
use Nobuhiko\EccubeCloudLogging\Processor\RequestProcessor;
use Nobuhiko\EccubeCloudLogging\Processor\UserProcessor;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{
    private function record(callable $processor, array $context = [])
    {
        $handler = new TestHandler();
        $logger = new Logger('app', [$handler], [$processor]);
        $logger->info('m', $context);

        return $handler->getRecords()[0];
    }

    public function testPii_入れ子の配列もたどり大文字小文字を区別しない(): void
    {
        $record = $this->record(new PiiProcessor(['order_email', 'Password']), [
            'order_id' => 1,
            'ORDER_EMAIL' => 'a@example.com',
            'order' => ['order_email' => 'b@example.com', 'total' => 100, 'items' => [['password' => 'x']]],
            0 => 'order_email',
        ]);

        $this->assertSame(1, $record['context']['order_id']);
        $this->assertSame('***', $record['context']['ORDER_EMAIL']);
        $this->assertSame('***', $record['context']['order']['order_email']);
        $this->assertSame(100, $record['context']['order']['total']);
        $this->assertSame('***', $record['context']['order']['items'][0]['password']);
        $this->assertSame('order_email', $record['context'][0], '値は伏せない（キーだけを見る）');
    }

    public function testRequest_XCloudTraceContextからtraceを取る(): void
    {
        $record = $this->record(new RequestProcessor('p', [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_X_CLOUD_TRACE_CONTEXT' => '0123456789abcdef0123456789abcdef/123;o=1',
        ]));

        $this->assertSame('projects/p/traces/0123456789abcdef0123456789abcdef', $record['extra']['trace']);
        $this->assertArrayNotHasKey('spanId', $record['extra'], '10 進の span は使わない');
    }

    public function testRequest_プロジェクトIDが無ければtraceを付けない(): void
    {
        $record = $this->record(new RequestProcessor('', [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_TRACEPARENT' => '00-0123456789abcdef0123456789abcdef-0123456789abcdef-01',
        ]));

        $this->assertArrayNotHasKey('trace', $record['extra']);
    }

    public function testRequest_おかしなtraceは付けない(): void
    {
        $record = $this->record(new RequestProcessor('p', [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_X_CLOUD_TRACE_CONTEXT' => '../../etc/passwd',
        ]));

        $this->assertArrayNotHasKey('trace', $record['extra']);
    }

    public function testRequest_CLIはscriptだけ(): void
    {
        $record = $this->record(new RequestProcessor('', ['SCRIPT_NAME' => '/var/www/data/cli/batch.php']));

        $this->assertSame('batch.php', $record['extra']['script']);
        $this->assertArrayNotHasKey('path', $record['extra']);
    }

    public function testUser_例外や空のときは何も足さない(): void
    {
        $record = $this->record(new UserProcessor(function () {
            throw new \RuntimeException('x');
        }));
        $this->assertSame([], $record['extra']);

        $record = $this->record(new UserProcessor(function () {
            return [];
        }));
        $this->assertSame([], $record['extra']);
    }
}
