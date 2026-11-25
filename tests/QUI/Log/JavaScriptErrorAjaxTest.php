<?php

namespace QUI\Log\Tests\QUI\Log;

use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config as QuiqqerConfig;
use QUI\Log\Config;
use QUI\Log\Logger;

class JavaScriptErrorAjaxTest extends TestCase
{
    private MonologLogger $OriginalLogger;
    private TestHandler $Handler;
    private QuiqqerConfig $PackageConfig;

    /** @var array<string, mixed> */
    private array $OriginalLogLevels;

    protected function setUp(): void
    {
        $this->OriginalLogger = Logger::getLogger();
        $this->Handler = new TestHandler();
        Logger::$Logger = new MonologLogger('test', [$this->Handler]);

        $PackageConfig = Config::getPackageConfig();
        self::assertNotNull($PackageConfig);
        $this->PackageConfig = $PackageConfig;
        $this->OriginalLogLevels = $PackageConfig->get('log_levels');
        $PackageConfig->setValue('log_levels', 'error', 1);

        require_once __DIR__ . '/../../../ajax/logJsError.php';
    }

    protected function tearDown(): void
    {
        Logger::$Logger = $this->OriginalLogger;
        $this->PackageConfig->setSection('log_levels', $this->OriginalLogLevels);
    }

    public function testAjaxPersistsGroupedCountColumnAndStack(): void
    {
        $stack = "TypeError: failed\n    at basket.js:42:16";
        $result = QUI::getAjax()->callRequestFunction('package_quiqqer_log_ajax_logJsError', [
            'errMsg' => 'TypeError: failed',
            'errUrl' => '/basket.js',
            'errLineNumber' => '42',
            'browser' => 'Safari',
            'context' => '[{"method":"getProductData"}]',
            'occurrences' => '9',
            'errColumnNumber' => '16',
            'errStack' => $stack
        ]);

        self::assertArrayHasKey('result', $result);
        $records = $this->Handler->getRecords();
        self::assertCount(1, $records);
        self::assertStringContainsString("Line Number: 42\nColumn Number: 16\n", $records[0]->message);
        self::assertStringContainsString("Occurrences: 9\n", $records[0]->message);
        self::assertStringContainsString("Stacktrace:\n$stack\n", $records[0]->message);
        self::assertStringContainsString('getProductData', $records[0]->message);
    }

    public function testOldClientsWithoutNewParametersRemainCompatible(): void
    {
        $result = QUI::getAjax()->callRequestFunction('package_quiqqer_log_ajax_logJsError', [
            'errMsg' => 'Script error.',
            'errUrl' => '',
            'errLineNumber' => '0',
            'browser' => 'Safari Brave',
            'context' => ''
        ]);

        self::assertArrayHasKey('result', $result);
        $records = $this->Handler->getRecords();
        self::assertCount(1, $records);
        self::assertStringContainsString("Error: Script error.\n", $records[0]->message);
        self::assertStringContainsString("Occurrences: 1\n", $records[0]->message);
        self::assertStringContainsString("Column Number: 0\n", $records[0]->message);
        self::assertStringNotContainsString('Stacktrace:', $records[0]->message);
    }

    public function testWalletErrorsAreAlsoFilteredForOldClients(): void
    {
        $result = QUI::getAjax()->callRequestFunction('package_quiqqer_log_ajax_logJsError', [
            'errMsg' => "TypeError: undefined is not an object (evaluating 'window.ethereum.selectedAddress = undefined')",
            'errUrl' => '/product',
            'errLineNumber' => '1',
            'browser' => 'Safari Brave',
            'context' => ''
        ]);

        self::assertArrayHasKey('result', $result);
        self::assertSame([], $this->Handler->getRecords());
    }
}
