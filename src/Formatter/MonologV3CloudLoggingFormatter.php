<?php

namespace Nobuhiko\EccubeCloudLogging\Formatter;

use DateTimeInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Monolog 3 用。CloudLogging::create() から使う（Monolog 2 で読み込むと型が合わずに落ちる）。
 */
class MonologV3CloudLoggingFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct();
        // datetime は normalizeRecord で文字列になるので、その時点で RFC 3339 にしておく
        $this->setDateFormat(DateTimeInterface::RFC3339_EXTENDED);
    }

    protected function normalizeRecord(LogRecord $record): array
    {
        return CloudLogging::fields(parent::normalizeRecord($record));
    }
}
