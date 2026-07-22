<?php

namespace QUI\Log\Tests\QUI\Log\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use QUI\Log\Monolog\QuiqqerFileFormatter;
use RuntimeException;

class QuiqqerFileFormatterTest extends TestCase
{
    public function testRecordWithoutContextOrExtraStaysOnOneLine(): void
    {
        $Formatter = new QuiqqerFileFormatter();
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2030-01-02 23:59:59.123456+14:00'),
            channel: 'test',
            level: Level::Info,
            message: 'Everything is fine'
        );

        self::assertSame(
            '[2030-01-02T23:59:59.123456+14:00] INFO: Everything is fine' . PHP_EOL,
            $Formatter->format($record)
        );
    }

    public function testCompleteRecordIsFormatted(): void
    {
        $Formatter = new QuiqqerFileFormatter();
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2030-01-02 23:59:59.123456+14:00'),
            channel: 'test',
            level: Level::Warning,
            message: 'Something happened',
            context: ['operation' => 'test'],
            extra: ['quiqqer' => ['filename' => 'auth']]
        );

        self::assertSame(
            <<<'LOG'
[2030-01-02T23:59:59.123456+14:00] WARNING: Something happened
{
    "context": {
        "operation": "test"
    },
    "extra": {
        "quiqqer": {
            "filename": "auth"
        }
    }
}
LOG . PHP_EOL,
            $Formatter->format($record)
        );
    }

    public function testExceptionIsNormalized(): void
    {
        $Formatter = new QuiqqerFileFormatter();
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Something failed',
            context: ['exception' => new RuntimeException('Something failed', 42)]
        );

        $formattedRecord = $Formatter->format($record);

        self::assertSame(
            [
                'classIsPresent' => true,
                'messageIsPresent' => true,
                'codeIsPresent' => true
            ],
            [
                'classIsPresent' => str_contains($formattedRecord, '"class": "RuntimeException"'),
                'messageIsPresent' => str_contains($formattedRecord, '"message": "Something failed"'),
                'codeIsPresent' => str_contains($formattedRecord, '"code": 42')
            ]
        );
    }
}
