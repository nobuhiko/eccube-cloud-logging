# eccube-cloud-logging

EC-CUBE を Cloud Run などのコンテナで動かすときのログの部品です。

- 標準出力に **Cloud Logging が読む JSON** を 1 行ずつ出す（`severity`・`time`、channel は `labels`、リクエストの `trace`）
- context の**個人情報を伏せる**（受注・会員の行を丸ごと渡しても、名前・住所・電話・メールは `***`）
- リクエストの情報（method・クエリを除いた path・ip）と、**ログイン中の利用者の ID** を足す
- 手元ではファイルに読みやすい 1 行のテキストで書き、古いものは日数で消す
- ログが書けなくても例外にせず、決済やエラー画面の処理を止めない

Monolog 2 / 3（PHP 7.4 以上）で動きます。EC-CUBE 2 系は古い入口（`GC_Utils::gfPrintLog`）につなぐ部品つき、4 系は Monolog の部品だけを使います。

## 入れ方

```bash
composer require nobuhiko/eccube-cloud-logging
```

## EC-CUBE 2 系

`GC_Utils::gfPrintLog()` の中身を置き換えます。

```php
public static function gfPrintLog($mess, $path = '', $verbose = USE_VERBOSE_LOG)
{
    \Nobuhiko\EccubeCloudLogging\Eccube2\Log::printLog($mess, $path);
}
```

- もとのログファイルの名前（`site.log`・`error.log`・`sps.log` …）が channel になります。日付つきの名前（`mdl_xxx_20260929.log`）は日付を外します
- `error.log` は ERROR、それ以外は INFO で書きます。アラートで拾いたい失敗は、呼び出し側を次の形にします

```php
use Nobuhiko\EccubeCloudLogging\Eccube2\Log;

Log::write('payment', 'error', '金額が合わない', ['order_id' => $orderId]);
```

### 設定（環境変数）

| 変数 | 値 | 既定 |
|---|---|---|
| `LOG_OUTPUT` | `stdout`: 標準出力に Cloud Logging の JSON / `file`: `DATA_REALDIR/logs/app-YYYY-MM-DD.log` | `file` |
| `LOG_LEVEL` | 出す重要度の下限（`debug`・`info`・`warning`・`error` …） | `debug` |
| `LOG_MAX_FILES` | `file` のときに残す日数。`0` なら消さない | `14` |
| `GOOGLE_CLOUD_PROJECT` | あれば Cloud Run のリクエストの trace を付ける（同じリクエストのログをまとめて見られる） | なし |

コードで上書きするときは、使う前に 1 回 `Log::configure()` を呼びます。

```php
use Nobuhiko\EccubeCloudLogging\Eccube2\Log;
use Nobuhiko\EccubeCloudLogging\Eccube2\PiiKeys;

Log::configure([
    // 独自に足した列も伏せる
    'pii_keys' => array_merge(PiiKeys::DEFAULT, ['hotel_tel', 'sender_addr']),
    // ログイン中の利用者の取り方（既定は管理者の member_id と会員の customer_id）
    'user' => static function (): array {
        return isset($_SESSION['login_id']) ? ['login_id' => $_SESSION['login_id']] : [];
    },
]);
```

## EC-CUBE 4 系（Symfony）と、ほかの Monolog

整形と processor をそのまま使えます。

```php
use Nobuhiko\EccubeCloudLogging\Formatter\CloudLogging;
use Nobuhiko\EccubeCloudLogging\Processor\PiiProcessor;
use Nobuhiko\EccubeCloudLogging\Processor\RequestProcessor;

$handler->setFormatter(CloudLogging::create());   // Monolog 2 / 3 に合うものを選ぶ
$logger->pushProcessor(new PiiProcessor(['email', 'tel', 'addr01']));
$logger->pushProcessor(new RequestProcessor());   // GOOGLE_CLOUD_PROJECT があれば trace も
```

`LoggerFactory::create()` で、出し先（stdout / file）と processor をまとめて組み立てることもできます。

## 出力の例

```json
{"message":"金額が合わない","context":{"order_id":12,"order_email":"***"},"channel":"payment","extra":{"method":"POST","path":"/shopping/confirm.php","ip":"203.0.113.1","member_id":3},"severity":"ERROR","time":"2026-11-05T15:04:05.123+09:00","logging.googleapis.com/labels":{"channel":"payment"},"logging.googleapis.com/trace":"projects/my-project/traces/0123456789abcdef0123456789abcdef"}
```

Cloud Logging では `labels.channel="payment"`、`severity>=ERROR`、`jsonPayload.context.order_id=12` のように絞れます。

## 気をつけること

- 伏せるのは context のキーだけです。メッセージの文字列に個人情報を混ぜないでください（`print_r` で配列を丸ごと文字列にするなど）
- path にクエリは入れませんが、Cloud Run や CDN 自身のアクセスログには URL が残ります。URL にトークンを載せる作りは別に見直してください

## ライセンス

MIT
