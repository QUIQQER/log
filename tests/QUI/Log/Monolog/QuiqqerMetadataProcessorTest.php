<?php

namespace QUI\Log\Tests\QUI\Log\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use QUI\Log\Monolog\QuiqqerMetadataProcessor;

class QuiqqerMetadataProcessorTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $OriginalServer;

    /**
     * @var array<string, mixed>
     */
    private array $OriginalRequest;

    protected function setUp(): void
    {
        $this->OriginalServer = $_SERVER;
        $this->OriginalRequest = $_REQUEST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->OriginalServer;
        $_REQUEST = $this->OriginalRequest;
    }

    public function testFilenameIsMovedFromContextToExtra(): void
    {
        $Processor = new QuiqqerMetadataProcessor();
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'test',
            context: ['filename' => 'auth', 'operation' => 'login'],
            extra: ['correlationId' => '123']
        );

        $processedRecord = $Processor($record);

        self::assertSame(
            [
                'context' => ['operation' => 'login'],
                'filename' => 'auth',
                'correlationId' => '123'
            ],
            [
                'context' => $processedRecord->context,
                'filename' => $processedRecord->extra['quiqqer']['filename'],
                'correlationId' => $processedRecord->extra['correlationId']
            ]
        );
    }

    public function testExistingQuiqqerMetadataIsPreserved(): void
    {
        $Processor = new QuiqqerMetadataProcessor();
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'test',
            context: ['filename' => 'auth'],
            extra: ['quiqqer' => ['correlationId' => '123']]
        );

        $processedRecord = $Processor($record);

        self::assertSame('123', $processedRecord->extra['quiqqer']['correlationId']);
        self::assertSame('auth', $processedRecord->extra['quiqqer']['filename']);
    }

    public function testRequestMetadataIsAddedWithoutChangingApplicationContext(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/?test=1';
        $_REQUEST['quiqqerBundle'] = 'package_ajax_method';

        $Processor = new QuiqqerMetadataProcessor();
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'test',
            context: ['operation' => 'login']
        );

        $processedRecord = $Processor($record);

        self::assertSame(
            [
                'context' => ['operation' => 'login'],
                'request' => HOST . '/admin/?test=1',
                'ajaxBundler' => 'package_ajax_method'
            ],
            [
                'context' => $processedRecord->context,
                'request' => $processedRecord->extra['quiqqer']['request'],
                'ajaxBundler' => $processedRecord->extra['quiqqer']['ajaxBundler']
            ]
        );
    }

    public function testInvalidExistingQuiqqerMetadataIsReplaced(): void
    {
        $Processor = new QuiqqerMetadataProcessor();
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'test',
            context: ['filename' => 'auth'],
            extra: ['quiqqer' => 'invalid']
        );

        $processedRecord = $Processor($record);

        self::assertSame(['filename' => 'auth'], $processedRecord->extra['quiqqer']);
    }
}
