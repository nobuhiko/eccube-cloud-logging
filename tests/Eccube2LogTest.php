<?php

namespace Nobuhiko\EccubeCloudLogging\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Nobuhiko\EccubeCloudLogging\Eccube2\Log;
use Nobuhiko\EccubeCloudLogging\LoggerFactory;
use PHPUnit\Framework\TestCase;

class Eccube2LogTest extends TestCase
{
    /** @var TestHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        Log::setLogger(new Logger('app', [$this->handler]));
    }

    protected function tearDown(): void
    {
        Log::configure([]);
        $_SESSION = [];
    }

    public function testChannelFromPath_拡張子と日付を外す(): void
    {
        $this->assertSame('sps', Log::channelFromPath('/var/www/data/logs/sps.log'));
        $this->assertSame('mdl_yfc_paymentapi', Log::channelFromPath('/x/logs/mdl_yfc_paymentapi_20260929.log'));
        $this->assertSame('past_month_sales', Log::channelFromPath('/x/logs/past_month_sales_202610.log'));
        $this->assertSame('site', Log::channelFromPath('/x/logs/.log'));
    }

    public function testPrintLog_ファイル名をchannelにしてINFO_errorだけERROR(): void
    {
        Log::printLog('送信', '/x/logs/sps.log');
        Log::printLog('失敗', '/x/logs/error.log');
        Log::printLog('ok');

        $records = $this->handler->getRecords();
        $this->assertSame(['sps', 'INFO'], [$records[0]['channel'], $records[0]['level_name']]);
        $this->assertSame(['error', 'ERROR'], [$records[1]['channel'], $records[1]['level_name']]);
        $this->assertSame('site', $records[2]['channel']);
    }

    public function testPrintLog_UTF8でないものはUTF8にする(): void
    {
        Log::printLog(mb_convert_encoding('決済', 'SJIS-win', 'UTF-8'), '/x/logs/sps.log');

        $this->assertSame('決済', $this->handler->getRecords()[0]['message']);
    }

    public function testWrite_重要度を書き間違えても例外にしない(): void
    {
        Log::write('site', 'no-such-level', 'x');

        $this->assertTrue(true);
    }

    public function testDefaultUser_管理者と会員のIDだけ(): void
    {
        $_SESSION = ['member_id' => 2, 'customer' => ['customer_id' => 5, 'email' => 'a@example.com']];

        $this->assertSame(['member_id' => 2, 'customer_id' => 5], Log::defaultUser());
    }

    public function testConfigure_ファイルに日付つきで書いて既定の個人情報を伏せる(): void
    {
        $dir = sys_get_temp_dir() . '/eccube-cloud-logging-' . bin2hex(random_bytes(4));
        mkdir($dir);
        Log::configure(['output' => 'file', 'file' => $dir . '/app.log', 'project' => '']);

        Log::write('site', 'info', '受注', ['order_id' => 1, 'order_email' => 'a@example.com']);

        $files = glob($dir . '/app-*.log');
        $this->assertCount(1, $files);
        $line = (string) file_get_contents($files[0]);
        $this->assertStringContainsString('site.INFO: 受注', $line);
        $this->assertStringContainsString('"order_email":"***"', $line);
        $this->assertStringNotContainsString('a@example.com', $line);

        array_map('unlink', $files);
        rmdir($dir);
    }

    public function testMaxFiles_読めない値は既定(): void
    {
        $this->assertSame(0, LoggerFactory::maxFiles('0'));
        $this->assertSame(30, LoggerFactory::maxFiles(' 30 '));
        $this->assertSame(14, LoggerFactory::maxFiles('-1'));
        $this->assertSame(14, LoggerFactory::maxFiles(''));
        $this->assertSame(14, LoggerFactory::maxFiles(false));
        $this->assertSame(14, LoggerFactory::maxFiles(null));
    }
}
