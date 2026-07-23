<?php

namespace QUI\Log\Tests\QUI\Log;

use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\TestCase;
use QUI\Log\Logger;
use QUI\Log\Monolog\QuiqqerMetadataProcessor;
use QUI\System\Log;

class SystemLogIntegrationTest extends TestCase
{
    private MonologLogger $OriginalLogger;

    private TestHandler $Handler;

    protected function setUp(): void
    {
        $this->OriginalLogger = Logger::getLogger();
        $this->Handler = new TestHandler();

        $Logger = new MonologLogger('test', [$this->Handler]);
        $Logger->pushProcessor(new QuiqqerMetadataProcessor());
        Logger::$Logger = $Logger;
    }

    protected function tearDown(): void
    {
        Logger::$Logger = $this->OriginalLogger;
    }

    public function testFilenameIsPassedFromSystemLogToMonologMetadata(): void
    {
        Log::write('Login failed', Log::LEVEL_ERROR, filename: 'auth', force: true);
        $record = $this->Handler->getRecords()[0];

        self::assertSame(
            [
                'contextFilenameExists' => false,
                'extraFilename' => 'auth'
            ],
            [
                'contextFilenameExists' => array_key_exists('filename', $record->context),
                'extraFilename' => $record->extra['quiqqer']['filename']
            ]
        );
    }
}
