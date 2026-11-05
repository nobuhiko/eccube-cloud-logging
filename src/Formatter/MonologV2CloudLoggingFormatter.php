<?php

namespace Nobuhiko\EccubeCloudLogging\Formatter;

use Monolog\Formatter\JsonFormatter;

/**
 * Monolog 2 用。CloudLogging::create() から使う（Monolog 3 で読み込むと型が合わずに落ちる）。
 */
class MonologV2CloudLoggingFormatter extends JsonFormatter
{
    public function format(array $record): string
    {
        return parent::format(CloudLogging::fields($record));
    }
}
