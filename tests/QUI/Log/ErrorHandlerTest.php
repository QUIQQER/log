<?php

namespace QUI\Log\Tests\QUI\Log;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config as QuiqqerConfig;
use QUI\Log\ErrorHandler;
use QUI\Log\Logger;
use ReflectionMethod;

class ErrorHandlerTest extends TestCase
{
    private int $OriginalErrorReportingLevel;

    private MonologLogger $OriginalLogger;

    private TestHandler $Handler;

    private QuiqqerConfig $PackageConfig;

    /**
     * @var array<string, mixed>
     */
    private array $OriginalLogLevels;

    /**
     * @var array<string, bool>
     */
    private array $OriginalLoggerLogLevels;

    /**
     * @var array<string, mixed>
     */
    private array $OriginalGlobalConfig;

    protected function setUp(): void
    {
        $this->OriginalErrorReportingLevel = error_reporting();
        error_reporting(E_ALL | E_STRICT);
        $this->OriginalLogger = Logger::getLogger();
        $this->Handler = new TestHandler();
        Logger::$Logger = new MonologLogger('test', [$this->Handler]);

        $PackageConfig = Logger::getPackage()->getConfig();
        self::assertNotNull($PackageConfig);
        $this->PackageConfig = $PackageConfig;
        $this->OriginalLogLevels = $PackageConfig->get('log_levels');
        $this->OriginalLoggerLogLevels = Logger::$logLevels;

        foreach (array_keys($this->OriginalLogLevels) as $level) {
            $PackageConfig->setValue('log_levels', $level, 1);
        }

        foreach (array_keys(Logger::$logLevels) as $level) {
            Logger::$logLevels[$level] = true;
        }

        self::assertNotNull(QUI::$Conf);
        $this->OriginalGlobalConfig = QUI::$Conf->get('globals');
        QUI::$Conf->setValue('globals', 'log_deprecated_errors', 1);
    }

    protected function tearDown(): void
    {
        error_reporting($this->OriginalErrorReportingLevel);
        Logger::$Logger = $this->OriginalLogger;
        Logger::$logLevels = $this->OriginalLoggerLogLevels;
        $this->PackageConfig->setSection('log_levels', $this->OriginalLogLevels);
        QUI::$Conf?->setSection('globals', $this->OriginalGlobalConfig);
    }

    /**
     * @return iterable<string, array{int, Level}>
     */
    public static function errorLevelProvider(): iterable
    {
        yield 'deprecated' => [E_DEPRECATED, Level::Warning];
        yield 'user deprecated' => [E_USER_DEPRECATED, Level::Warning];
        yield 'notice' => [E_NOTICE, Level::Notice];
        yield 'user notice' => [E_USER_NOTICE, Level::Notice];
        yield 'strict' => [E_STRICT, Level::Notice];
        yield 'warning' => [E_WARNING, Level::Warning];
        yield 'user warning' => [E_USER_WARNING, Level::Warning];
        yield 'recoverable error' => [E_RECOVERABLE_ERROR, Level::Error];
        yield 'unknown levels retain the error fallback' => [123456, Level::Error];
    }

    #[DataProvider('errorLevelProvider')]
    public function testHandleErrorMapsPhpErrorsToLogLevels(int $errorLevel, Level $expectedLevel): void
    {
        self::assertTrue(ErrorHandler::handleError($errorLevel, 'Something happened', '/tmp/source.php', 42));

        $record = $this->Handler->getRecords()[0];
        self::assertSame(
            [
                'level' => $expectedLevel,
                'message' => 'Something happened',
                'file' => '/tmp/source.php',
                'line' => 42
            ],
            [
                'level' => $record->level,
                'message' => $record->message,
                'file' => $record->context['file'],
                'line' => $record->context['line']
            ]
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredErrorProvider(): iterable
    {
        yield 'regenerate session ID' => ['session_regenerate_id(): Session ID cannot be regenerated'];
        yield 'destroy session' => ['session_destroy(): Trying to destroy uninitialized session'];
        yield 'legacy parameter order' => ['Required parameter $permissions follows optional parameter $path'];
    }

    #[DataProvider('ignoredErrorProvider')]
    public function testKnownLegacyErrorsAreIgnored(string $message): void
    {
        self::assertTrue(ErrorHandler::handleError(E_WARNING, $message, '/tmp/source.php', 42));
        self::assertSame([], $this->Handler->getRecords());
    }

    public function testErrorsOutsideTheCurrentReportingMaskAreNotHandledOrLogged(): void
    {
        error_reporting(E_ALL & ~E_USER_WARNING);

        self::assertFalse(
            ErrorHandler::handleError(E_USER_WARNING, 'Suppressed warning', '/tmp/source.php', 42)
        );
        self::assertSame([], $this->Handler->getRecords());
    }

    public function testPhpErrorReportingIncludesEveryConfiguredLevel(): void
    {
        self::assertSame(E_ALL, $this->getPhpErrorReportingLevel());
    }

    public function testPhpErrorReportingExcludesDisabledLevels(): void
    {
        foreach (array_keys($this->OriginalLogLevels) as $level) {
            $this->PackageConfig->setValue('log_levels', $level, 0);
        }
        QUI::$Conf?->setValue('globals', 'log_deprecated_errors', 0);

        $expectedLevel = E_ALL
            & ~E_DEPRECATED
            & ~E_USER_DEPRECATED
            & ~E_PARSE
            & ~E_ERROR
            & ~E_CORE_ERROR
            & ~E_COMPILE_ERROR
            & ~E_USER_ERROR
            & ~E_RECOVERABLE_ERROR
            & ~E_WARNING
            & ~E_USER_WARNING
            & ~E_CORE_WARNING
            & ~E_COMPILE_WARNING
            & ~E_NOTICE
            & ~E_USER_NOTICE
            & ~E_STRICT;

        self::assertSame($expectedLevel, $this->getPhpErrorReportingLevel());
    }

    private function getPhpErrorReportingLevel(): int
    {
        $Method = new ReflectionMethod(ErrorHandler::class, 'getPhpErrorReportingLevel');

        return $Method->invoke(null);
    }
}
