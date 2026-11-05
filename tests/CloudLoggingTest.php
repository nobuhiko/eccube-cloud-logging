<?php

namespace Nobuhiko\EccubeCloudLogging\Tests;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Nobuhiko\EccubeCloudLogging\Formatter\CloudLogging;
use Nobuhiko\EccubeCloudLogging\Processor\PiiProcessor;
use Nobuhiko\EccubeCloudLogging\Processor\RequestProcessor;
use Nobuhiko\EccubeCloudLogging\Processor\UserProcessor;
use PHPUnit\Framework\TestCase;

/**
 * 標準出力に出す JSON の 1 行（Monolog 2 / 3 のどちらでも同じ形になること）。
 */
class CloudLoggingTest extends TestCase
{
    /**
     * @param callable[] $processors
     */
    private function logLine(callable $write, array $processors = []): array
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);
        $handler->setFormatter(CloudLogging::create());
        $logger = new Logger('app', [$handler]);
        foreach (array_reverse($processors) as $processor) {
            $logger->pushProcessor($processor);
        }

        $write($logger);

        rewind($stream);
        $lines = array_values(array_filter(explode("\n", (string) stream_get_contents($stream))));
        $this->assertCount(1, $lines, '1 件で 1 行');

        return json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSeverityとtimeとlabelsとtraceを付ける(): void
    {
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/shopping/confirm.php?token=secret',
            'REMOTE_ADDR' => '203.0.113.1',
            'HTTP_TRACEPARENT' => '00-0123456789abcdef0123456789abcdef-0123456789abcdef-01',
        ];
        $json = $this->logLine(
            function (Logger $logger) {
                $logger->withName('payment')->error('金額が合わない', ['order_id' => 12, 'order_email' => 'a@example.com']);
            },
            [
                new RequestProcessor('my-project', $server),
                new UserProcessor(function () {
                    return ['member_id' => 3];
                }),
                new PiiProcessor(['order_email']),
            ]
        );

        $this->assertSame('ERROR', $json['severity']);
        $this->assertSame('金額が合わない', $json['message']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $json['time']);
        $this->assertSame(['channel' => 'payment'], $json['logging.googleapis.com/labels']);
        $this->assertSame('projects/my-project/traces/0123456789abcdef0123456789abcdef', $json['logging.googleapis.com/trace']);
        $this->assertSame('0123456789abcdef', $json['logging.googleapis.com/spanId']);
        $this->assertSame(12, $json['context']['order_id']);
        $this->assertSame('***', $json['context']['order_email']);
        $this->assertSame('/shopping/confirm.php', $json['extra']['path'], 'クエリは書かない');
        $this->assertSame('POST', $json['extra']['method']);
        $this->assertSame('203.0.113.1', $json['extra']['ip']);
        $this->assertSame(3, $json['extra']['member_id']);
        $this->assertArrayNotHasKey('trace', $json['extra']);
        $this->assertArrayNotHasKey('level', $json);
        $this->assertArrayNotHasKey('level_name', $json);
        $this->assertArrayNotHasKey('datetime', $json);
    }

    public function test日本語はエスケープしない(): void
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);
        $handler->setFormatter(CloudLogging::create());
        (new Logger('app', [$handler]))->info('受注');
        rewind($stream);

        $this->assertStringContainsString('"受注"', (string) stream_get_contents($stream));
    }

    public function testDEBUGとCRITICALはそのままの名前のseverity(): void
    {
        $json = $this->logLine(function (Logger $logger) {
            $logger->critical('x');
        });
        $this->assertSame('CRITICAL', $json['severity']);
    }
}
