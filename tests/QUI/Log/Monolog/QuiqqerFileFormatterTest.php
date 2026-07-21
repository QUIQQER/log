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

        self::assertStringContainsString(
            '"class": "RuntimeException"',
            $Formatter->format($record)
        );
    }
}
