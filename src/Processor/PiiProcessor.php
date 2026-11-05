<?php

namespace Nobuhiko\EccubeCloudLogging\Processor;

use Monolog\LogRecord;
use Nobuhiko\EccubeCloudLogging\Record;

/**
 * context の個人情報・秘密のキーの値を伏せる（Monolog の processor。Monolog 2 / 3 のどちらでも使える）。
 *
 * 受注や会員の行を丸ごと context に渡す呼び出しが多いので、呼び出し側ではなくここで伏せる。
 * 入れ子の配列（['order' => $arrOrder] など）の中もたどる。キーは大文字・小文字を区別しない。
 */
class PiiProcessor
{
    /** @var array<string, true> */
    private $keys = [];

    /** @var string */
    private $mask;

    /**
     * @param string[] $keys 伏せるキー（EC-CUBE 2 系の既定は Eccube2\PiiKeys::DEFAULT）
     */
    public function __construct(array $keys, string $mask = '***')
    {
        foreach ($keys as $key) {
            $this->keys[strtolower((string) $key)] = true;
        }
        $this->mask = $mask;
    }

    /**
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public function __invoke($record)
    {
        $context = Record::context($record);
        if ($context === []) {
            return $record;
        }

        return Record::withContext($record, $this->scrub($context));
    }

    public function scrub(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && isset($this->keys[strtolower($key)])) {
                $values[$key] = $this->mask;
            } elseif (is_array($value)) {
                $values[$key] = $this->scrub($value);
            }
        }

        return $values;
    }
}
