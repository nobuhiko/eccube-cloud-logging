<?php

namespace Nobuhiko\EccubeCloudLogging\Formatter;

use DateTimeInterface;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Cloud Logging が読む JSON の 1 行にする整形（標準出力に出し、Cloud Run などが拾う）。
 *
 * Monolog の GoogleCloudLoggingFormatter と同じく severity と time を付け、加えて
 * channel を labels に、extra の trace・spanId を Cloud Logging の項目に移す
 * （GoogleCloudLoggingFormatter は final で、labels と trace を足せない）。
 * https://cloud.google.com/logging/docs/structured-logging
 *
 * Monolog 2 と 3 でメソッドの型が違うので、版に合うクラスを create() で選ぶ。
 */
final class CloudLogging
{
    public static function create(): FormatterInterface
    {
        return class_exists(LogRecord::class)
            ? new MonologV3CloudLoggingFormatter()
            : new MonologV2CloudLoggingFormatter();
    }

    /**
     * Monolog のレコード（配列）を Cloud Logging の項目に並べ替える。
     *
     * @param array $record message・context・level_name・channel・datetime・extra を持つ配列
     */
    public static function fields(array $record): array
    {
        $record['severity'] = $record['level_name'] ?? 'DEFAULT';
        $datetime = $record['datetime'] ?? null;
        if ($datetime instanceof DateTimeInterface) {
            $datetime = $datetime->format(DateTimeInterface::RFC3339_EXTENDED);
        }
        if ($datetime !== null) {
            $record['time'] = $datetime;
        }
        $record['logging.googleapis.com/labels'] = ['channel' => (string) ($record['channel'] ?? '')];

        // Monolog 3 は空の extra を {} と出すためにオブジェクトにしている
        if (array_key_exists('extra', $record)) {
            $extra = (array) $record['extra'];
            foreach (['trace', 'spanId'] as $key) {
                if (isset($extra[$key])) {
                    $record['logging.googleapis.com/' . $key] = $extra[$key];
                    unset($extra[$key]);
                }
            }
            $record['extra'] = $extra === [] ? new \stdClass() : $extra;
        }

        unset($record['level'], $record['level_name'], $record['datetime']);

        return $record;
    }
}
