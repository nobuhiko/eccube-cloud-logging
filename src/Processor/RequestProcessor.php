<?php

namespace Nobuhiko\EccubeCloudLogging\Processor;

use Monolog\LogRecord;
use Nobuhiko\EccubeCloudLogging\Record;

/**
 * リクエストの情報を extra に足す（Monolog の processor。Monolog 2 / 3 のどちらでも使える）。
 *
 * - Web: method・path・ip。path にはクエリを含めない（トークンなどが入り得るため）
 * - CLI: script（実行したスクリプトの名前）
 * - Cloud Run などが付ける trace（traceparent か X-Cloud-Trace-Context）。プロジェクト ID があるときだけ。
 *   CloudLogging の整形が Cloud Logging の trace の項目に移し、同じリクエストのログをまとめて見られるようになる
 */
class RequestProcessor
{
    /** @var string */
    private $project;

    /** @var array|null */
    private $server;

    /**
     * @param string|null $project GCP のプロジェクト ID。null なら環境変数 GOOGLE_CLOUD_PROJECT
     * @param array|null $server $_SERVER の代わり（テスト用）。null なら $_SERVER
     */
    public function __construct(?string $project = null, ?array $server = null)
    {
        $this->project = $project ?? (string) getenv('GOOGLE_CLOUD_PROJECT');
        $this->server = $server;
    }

    /**
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public function __invoke($record)
    {
        $server = $this->server ?? $_SERVER;
        $extra = Record::extra($record);

        if (PHP_SAPI !== 'cli' || isset($server['REQUEST_METHOD'])) {
            $extra['method'] = (string) ($server['REQUEST_METHOD'] ?? '');
            $extra['path'] = (string) parse_url((string) ($server['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            $extra['ip'] = (string) ($server['REMOTE_ADDR'] ?? '');
        } else {
            $extra['script'] = basename((string) ($server['SCRIPT_NAME'] ?? ''));
        }

        if ($this->project !== '') {
            [$trace, $span] = self::trace($server);
            if ($trace !== null) {
                $extra['trace'] = "projects/{$this->project}/traces/{$trace}";
                if ($span !== null) {
                    $extra['spanId'] = $span;
                }
            }
        }

        return Record::withExtra($record, $extra);
    }

    /**
     * @return array{0: string|null, 1: string|null} trace ID（16 進 32 桁）と span ID（16 進 16 桁）
     */
    private static function trace(array $server): array
    {
        // W3C の traceparent: 00-<trace 32 桁>-<span 16 桁>-<flags>
        $parent = (string) ($server['HTTP_TRACEPARENT'] ?? '');
        if (preg_match('/^[0-9a-f]{2}-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/', $parent, $m)) {
            return [$m[1], $m[2]];
        }

        // Google の X-Cloud-Trace-Context: TRACE_ID/SPAN_ID;o=1（span は 10 進なので使わない）
        $context = (string) ($server['HTTP_X_CLOUD_TRACE_CONTEXT'] ?? '');
        $trace = explode('/', $context)[0];
        if (preg_match('/^[0-9a-f]{32}$/', $trace)) {
            return [$trace, null];
        }

        return [null, null];
    }
}
