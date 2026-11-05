<?php

namespace Nobuhiko\EccubeCloudLogging\Eccube2;

use Monolog\Logger;
use Nobuhiko\EccubeCloudLogging\LoggerFactory;
use Nobuhiko\EccubeCloudLogging\Processor\PiiProcessor;
use Nobuhiko\EccubeCloudLogging\Processor\RequestProcessor;
use Nobuhiko\EccubeCloudLogging\Processor\UserProcessor;
use Psr\Log\LoggerInterface;

/**
 * EC-CUBE 2 系のログの入口。GC_Utils::gfPrintLog などの古い入口から呼ぶ。
 *
 *   // GC_Utils::gfPrintLog() の中身をこれに置き換える
 *   \Nobuhiko\EccubeCloudLogging\Eccube2\Log::printLog($mess, $path);
 *
 *   // 新しく書くとき
 *   Log::write('payment', 'error', '金額が合わない', ['order_id' => $orderId]);
 *
 * 古いログファイルの名前（site.log・error.log・sps.log …）を channel にし、error.log だけ ERROR、ほかは INFO で書く。
 * 設定は環境変数から読む。configure() で上書きできる（使う前に 1 回）。
 *
 * - LOG_OUTPUT: stdout（Cloud Logging の JSON）か file（既定。DATA_REALDIR/logs/app-YYYY-MM-DD.log）
 * - LOG_LEVEL: 出す重要度の下限。既定 debug
 * - LOG_MAX_FILES: file のときに残す日数。既定 14、0 なら消さない
 * - GOOGLE_CLOUD_PROJECT: あれば Cloud Run のリクエストの trace を付ける
 */
final class Log
{
    /** @var array */
    private static $options = [];

    /** @var Logger|LoggerInterface|null */
    private static $logger;

    /** @var LoggerInterface[] */
    private static $channels = [];

    /**
     * 設定を上書きする。渡さなかったものは環境変数か既定のまま。
     *
     * @param array{output?: string, level?: string|int, file?: string, max_files?: int|string, project?: string,
     *              pii_keys?: string[], user?: callable(): array, processors?: callable[]} $options
     */
    public static function configure(array $options): void
    {
        self::$options = $options;
        self::$logger = null;
        self::$channels = [];
    }

    /**
     * 出し先を差し替える（テスト用）。null で設定から作り直す。
     */
    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
        self::$channels = [];
    }

    /**
     * 1 件書く。失敗しても例外にしない（決済やエラー画面の処理を止めない）。
     *
     * @param string $channel ログの種類（site・error・payment …）
     * @param string|int $level PSR-3 / Monolog の重要度（'info'・'error' など）
     */
    public static function write(string $channel, $level, $message, array $context = []): void
    {
        try {
            self::channel($channel)->log($level, (string) $message, $context);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 種類を決めたロガー（PSR-3）を返す。決済会社の SDK のようにロガーを渡すところ用。
     */
    public static function channel(string $channel): LoggerInterface
    {
        if (!isset(self::$channels[$channel])) {
            $logger = self::logger();
            self::$channels[$channel] = $logger instanceof Logger ? $logger->withName($channel) : $logger;
        }

        return self::$channels[$channel];
    }

    /**
     * GC_Utils::gfPrintLog() の代わり。ファイル名を channel にし、error.log は ERROR、ほかは INFO。
     *
     * @param string $path もとのログファイルのパス。空なら site
     */
    public static function printLog($message, string $path = ''): void
    {
        $channel = $path === '' ? 'site' : self::channelFromPath($path);

        // 決済会社の応答など UTF-8 でないものは UTF-8 にする（JSON にすると化けるため）
        $message = (string) $message;
        if (!mb_check_encoding($message, 'UTF-8')) {
            $message = mb_convert_encoding($message, 'UTF-8', 'SJIS-win,eucJP-win');
        }

        self::write($channel, $channel === 'error' ? 'error' : 'info', $message);
    }

    /**
     * 古いログファイルのパスから channel を作る。
     * logs/sps.log → sps、mdl_yfc_paymentapi_20260929.log → mdl_yfc_paymentapi、past_month_202610.log → past_month
     */
    public static function channelFromPath(string $path): string
    {
        $name = preg_replace('/\.log$/', '', basename($path));
        $name = preg_replace('/_\d{6}(\d{2})?$/', '', (string) $name);

        return $name === '' ? 'site' : $name;
    }

    /**
     * ログイン中の利用者の既定の取り方（EC-CUBE 2 系の標準のセッション）。管理者は member_id、会員は customer_id。
     */
    public static function defaultUser(): array
    {
        $user = [];
        if (isset($_SESSION['member_id'])) {
            $user['member_id'] = $_SESSION['member_id'];
        }
        if (isset($_SESSION['customer']['customer_id'])) {
            $user['customer_id'] = $_SESSION['customer']['customer_id'];
        }

        return $user;
    }

    /**
     * @return Logger|LoggerInterface
     */
    private static function logger()
    {
        if (self::$logger === null) {
            $o = self::$options;
            $env = static function (string $name) {
                $value = getenv($name);

                return $value === false || $value === '' ? null : $value;
            };
            // DATA_REALDIR が決まる前（読み込みの途中の Fatal エラーなど）は、書き先のファイルが分からないので標準エラーへ
            $file = $o['file'] ?? (defined('DATA_REALDIR') ? DATA_REALDIR . 'logs/app.log' : 'php://stderr');

            $processors = array_merge($o['processors'] ?? [], [
                new RequestProcessor($o['project'] ?? $env('GOOGLE_CLOUD_PROJECT')),
                new UserProcessor($o['user'] ?? [self::class, 'defaultUser']),
                new PiiProcessor($o['pii_keys'] ?? PiiKeys::DEFAULT),
            ]);

            self::$logger = LoggerFactory::create(
                (string) ($o['output'] ?? $env('LOG_OUTPUT') ?? 'file'),
                $o['level'] ?? $env('LOG_LEVEL') ?? 'debug',
                $file,
                $o['max_files'] ?? $env('LOG_MAX_FILES'),
                $processors
            );
        }

        return self::$logger;
    }
}
