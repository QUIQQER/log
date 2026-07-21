<?php

namespace QUI\Log\Tests\QUI\Log\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use QUI\Log\Monolog\QuiqqerMetadataProcessor;

class QuiqqerMetadataProcessorTest extends TestCase
{
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
}
