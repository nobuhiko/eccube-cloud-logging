<?php

namespace Nobuhiko\EccubeCloudLogging;

use Monolog\LogRecord;

/**
 * Monolog 2（配列）と Monolog 3（LogRecord）のレコードを同じように扱う。
 */
final class Record
{
    /**
     * @param array|LogRecord $record
     */
    public static function context($record): array
    {
        return $record instanceof LogRecord ? $record->context : ($record['context'] ?? []);
    }

    /**
     * @param array|LogRecord $record
     */
    public static function extra($record): array
    {
        return $record instanceof LogRecord ? $record->extra : ($record['extra'] ?? []);
    }

    /**
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public static function withContext($record, array $context)
    {
        if ($record instanceof LogRecord) {
            // context は読み取り専用なので作り直す（名前つき引数は PHP 8 からなので、引数の順で渡す）
            return new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                $context,
                $record->extra,
                $record->formatted
            );
        }
        $record['context'] = $context;

        return $record;
    }

    /**
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public static function withExtra($record, array $extra)
    {
        if ($record instanceof LogRecord) {
            $record->extra = $extra;

            return $record;
        }
        $record['extra'] = $extra;

        return $record;
    }
}
