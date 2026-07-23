<?php

namespace QUI\Log\Tests\QUI\Log;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config as QuiqqerConfig;
use QUI\Log\Config;
use QUI\Log\Logger;

class ConfigTest extends TestCase
{
    private QuiqqerConfig $PackageConfig;

    /**
     * @var array<string, mixed>
     */
    private array $OriginalLogLevels;

    /**
     * @var array<string, mixed>
     */
    private array $OriginalLogConfig;

    /**
     * @var array<string, mixed>
     */
    private array $OriginalGlobalConfig;

    protected function setUp(): void
    {
        $PackageConfig = Config::getPackageConfig();
        self::assertNotNull($PackageConfig);

        $this->PackageConfig = $PackageConfig;
        $this->OriginalLogLevels = $PackageConfig->get('log_levels');
        $this->OriginalLogConfig = $PackageConfig->get('log');

        self::assertNotNull(QUI::$Conf);
        $this->OriginalGlobalConfig = QUI::$Conf->get('globals');
    }

    protected function tearDown(): void
    {
        $this->PackageConfig->setSection('log_levels', $this->OriginalLogLevels);
        $this->PackageConfig->setSection('log', $this->OriginalLogConfig);
        QUI::$Conf?->setSection('globals', $this->OriginalGlobalConfig);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function loggingLevelProvider(): iterable
    {
        yield 'debug' => ['debug', 'isDebugLoggingEnabled'];
        yield 'info' => ['info', 'isInfoLoggingEnabled'];
        yield 'notice' => ['notice', 'isNoticeLoggingEnabled'];
        yield 'warning' => ['warning', 'isWarningLoggingEnabled'];
        yield 'error' => ['error', 'isErrorLoggingEnabled'];
        yield 'critical' => ['critical', 'isCriticalLoggingEnabled'];
        yield 'alert' => ['alert', 'isAlertLoggingEnabled'];
        yield 'emergency' => ['emergency', 'isEmergencyLoggingEnabled'];
    }

    #[DataProvider('loggingLevelProvider')]
    public function testLoggingLevelMethodsReadThePackageConfiguration(string $level, string $method): void
    {
        $this->PackageConfig->setValue('log_levels', $level, 0);
        self::assertFalse(Config::$method());

        $this->PackageConfig->setValue('log_levels', $level, 1);
        self::assertTrue(Config::$method());
    }

    public function testDeprecationLoggingReadsTheGlobalConfiguration(): void
    {
        QUI::$Conf?->setValue('globals', 'log_deprecated_errors', 0);
        self::assertFalse(Config::isDeprecationLoggingEnabled());

        QUI::$Conf?->setValue('globals', 'log_deprecated_errors', 1);
        self::assertTrue(Config::isDeprecationLoggingEnabled());
    }

    public function testAllEventLoggingReadsThePackageConfiguration(): void
    {
        $this->PackageConfig->setValue('log', 'logAllEvents', 0);
        self::assertFalse(Config::isAllEventLoggingEnabled());

        $this->PackageConfig->setValue('log', 'logAllEvents', 1);
        self::assertTrue(Config::isAllEventLoggingEnabled());
    }
}
