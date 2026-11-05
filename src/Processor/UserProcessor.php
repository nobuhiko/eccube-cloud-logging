<?php

namespace Nobuhiko\EccubeCloudLogging\Processor;

use Monolog\LogRecord;
use Nobuhiko\EccubeCloudLogging\Record;

/**
 * ログイン中の利用者（会員 ID・管理者 ID など）を extra に足す（Monolog の processor）。
 *
 * 誰がログインしているかの取り方はアプリごとに違うので、取り出す関数を渡す。
 * 関数は extra に足す配列（['member_id' => 1] など）を返す。名前やメールなどの個人情報は返さない（ID だけにする）。
 */
class UserProcessor
{
    /** @var callable(): array */
    private $resolve;

    /**
     * @param callable(): array $resolve
     */
    public function __construct(callable $resolve)
    {
        $this->resolve = $resolve;
    }

    /**
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public function __invoke($record)
    {
        try {
            $user = ($this->resolve)();
        } catch (\Throwable $e) {
            // ログのために呼び出し元を止めない
            return $record;
        }
        if (!is_array($user) || $user === []) {
            return $record;
        }

        return Record::withExtra($record, array_merge(Record::extra($record), $user));
    }
}
