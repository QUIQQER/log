<?php

namespace QUI\Log\Tests\QUI\Log;

use Monolog\Handler\ChromePHPHandler;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\TestCase;
use QUI\Config;
use QUI\Log\Logger;
use QUI\Log\Monolog\LogHandlerV3;
use QUI\Log\Monolog\QuiqqerMetadataProcessor;
use QUI\Log\MonologConfigurator;

class MonologConfiguratorTest extends TestCase
{
    private MonologLogger $OriginalLogger;

    private Config $PackageConfig;

    /**
     * @var array<string, mixed>
     */
    private array $OriginalBrowserLogConfig;

    protected function setUp(): void
    {
        $this->OriginalLogger = Logger::getLogger();

        $PackageConfig = \QUI\Log\Config::getPackageConfig();
        self::assertNotNull($PackageConfig);
        $this->PackageConfig = $PackageConfig;
        $this->OriginalBrowserLogConfig = $PackageConfig->get('browser_logs');
    }

    protected function tearDown(): void
    {
        Logger::$Logger = $this->OriginalLogger;
        $this->PackageConfig->setSection('browser_logs', $this->OriginalBrowserLogConfig);
    }

    public function testQuiqqerHandlerFactoryDoesNotMutateLogger(): void
    {
        $Logger = new MonologLogger('test');
        $Handler = MonologConfigurator::createQuiqqerHandler();

        self::assertSame(LogHandlerV3::class, $Handler::class);
        self::assertSame([], $Logger->getHandlers());
        self::assertSame([], $Logger->getProcessors());
    }

    public function testCompleteConfigurationKeepsTheLocalHandlerFirst(): void
    {
        $this->PackageConfig->setSection('browser_logs', [
            'firephp' => 0,
            'chromephp' => 1,
            'browserphp' => 0,
            'userLogedIn' => 0
        ]);
        $Logger = new MonologLogger('test');

        MonologConfigurator::configure($Logger);
        $handlers = $Logger->getHandlers();

        self::assertSame(
            [
                'firstHandler' => LogHandlerV3::class,
                'processor' => QuiqqerMetadataProcessor::class
            ],
            [
                'firstHandler' => $handlers[0]::class,
                'processor' => $Logger->getProcessors()[0]::class
            ]
        );
    }

    public function testIndividualConfiguratorMethodsRetainPushHandlerSemantics(): void
    {
        $this->PackageConfig->setSection('browser_logs', [
            'firephp' => 0,
            'chromephp' => 1,
            'browserphp' => 0,
            'userLogedIn' => 0
        ]);
        $Logger = new MonologLogger('test');
        $Logger->pushHandler(MonologConfigurator::createQuiqqerHandler());

        Logger::addChromePHPHandlerToLogger($Logger);
        $handlers = $Logger->getHandlers();

        self::assertSame(
            [ChromePHPHandler::class, LogHandlerV3::class],
            [$handlers[0]::class, $handlers[1]::class]
        );
    }
}
