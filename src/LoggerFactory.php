<?php

namespace Nobuhiko\EccubeCloudLogging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;
use Nobuhiko\EccubeCloudLogging\Formatter\CloudLogging;

/**
 * 出し先を決めて Monolog の Logger を作る。
 *
 * - stdout: 標準出力に Cloud Logging の JSON を 1 行ずつ（Cloud Run など）。保存期間は出し先で決める
 * - file: 読みやすい 1 行のテキストを日ごとのファイルに足していく（手元の開発環境など）。古いものは消す
 *
 * ログが書けなくても（ディスクが一杯、設定の誤りなど）例外にせず、呼び出し元の処理を止めない。
 */
final class LoggerFactory
{
    public const DEFAULT_MAX_FILES = 14;

    /**
     * @param string $output stdout・file
     * @param string|int $level 出す重要度の下限（debug・info … や Monolog の定数）。読めない値なら debug
     * @param string $file file のときの書き先。日付を足した名前（app.log → app-2026-10-28.log）に書く。php:// のストリームならそのまま
     * @param int|string|false|null $maxFiles file のときに残す日数。0 なら消さない。読めない値（空・負・数でない）なら 14
     * @param callable[] $processors 足す processor（RequestProcessor・PiiProcessor・UserProcessor など）
     */
    public static function create(string $output, $level, string $file, $maxFiles = null, array $processors = [], string $name = 'app'): Logger
    {
        try {
            $level = Logger::toMonologLevel($level);
        } catch (\Throwable $e) {
            $level = Logger::toMonologLevel('debug');
        }

        if ($output === 'stdout') {
            $handler = new StreamHandler('php://stdout', $level);
            $handler->setFormatter(CloudLogging::create());
        } else {
            $handler = strpos($file, 'php://') === 0
                ? new StreamHandler($file, $level)
                : new RotatingFileHandler($file, self::maxFiles($maxFiles), $level);
            $handler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n", 'Y-m-d H:i:s', true, true));
        }

        $logger = new Logger($name);
        $logger->pushHandler(new WhatFailureGroupHandler([$handler]));
        // Monolog は最後に足したものから動かす。個人情報を伏せるのは最後にしたいので、先頭に置いたものを最後に足す
        foreach (array_reverse($processors) as $processor) {
            $logger->pushProcessor($processor);
        }

        return $logger;
    }

    /**
     * @param int|string|false|null $value
     */
    public static function maxFiles($value): int
    {
        $value = is_string($value) ? trim($value) : $value;
        if ((is_int($value) && $value >= 0) || (is_string($value) && $value !== '' && ctype_digit($value))) {
            return (int) $value;
        }

        return self::DEFAULT_MAX_FILES;
    }
}
