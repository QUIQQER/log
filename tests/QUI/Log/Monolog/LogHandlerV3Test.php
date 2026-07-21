<?php

namespace QUI\Log\Tests\QUI\Log\Monolog;

use DateTimeImmutable;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Log\Monolog\LogHandlerV3;

class LogHandlerV3Test extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function customFilenameProvider(): iterable
    {
        yield 'simple filename' => ['auth', 'auth'];
        yield 'zero filename' => ['0', '0'];
        yield 'supported separators' => ['mail.delivery_1', 'mail.delivery_1'];
        yield 'parent directory traversal' => ['../outside', 'outside'];
        yield 'Windows directory traversal' => ['..\\outside', 'outside'];
        yield 'absolute path' => ['/tmp/outside', 'tmp_outside'];
        yield 'empty filename' => ['', null];
        yield 'current directory' => ['.', null];
        yield 'parent directory' => ['..', null];
        yield 'whitespace' => ['custom log', 'custom_log'];
        yield 'unsafe characters' => ['custom:*?log', 'custom-_log'];
        yield 'non-string value' => [['outside'], null];
    }

    #[DataProvider('customFilenameProvider')]
    public function testCustomFilenameValidation(mixed $filename, ?string $expected): void
    {
        $Handler = new class () extends LogHandlerV3 {
            /**
             * @param LogRecord $record
             */
            public function getCustomFilenameForTest(LogRecord $record): ?string
            {
                return $this->getCustomFilename($record);
            }
        };

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'test',
            extra: ['quiqqer' => ['filename' => $filename]]
        );

        self::assertSame($expected, $Handler->getCustomFilenameForTest($record));
    }

    public function testCustomFilenameFallsBackToContext(): void
    {
        $Handler = new class () extends LogHandlerV3 {
            public function getCustomFilenameForTest(LogRecord $record): ?string
            {
                return $this->getCustomFilename($record);
            }
        };

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'test',
            context: ['filename' => 'legacy']
        );

        self::assertSame('legacy', $Handler->getCustomFilenameForTest($record));
    }

    public function testLogFilenameUsesRecordDate(): void
    {
        $Handler = new class () extends LogHandlerV3 {
            public function getLogFilenameForTest(LogRecord $record): string
            {
                return $this->getLogFilename($record);
            }
        };

        $record = new LogRecord(
            datetime: new DateTimeImmutable('2030-01-02 23:59:59+14:00'),
            channel: 'test',
            level: Level::Warning,
            message: 'test',
            extra: ['quiqqer' => ['filename' => 'auth']]
        );

        self::assertSame('auth-2030-01-02', $Handler->getLogFilenameForTest($record));
    }

    public function testConfiguredFormatterOutputIsAppended(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'quiqqer-log-handler-');
        self::assertNotFalse($file);

        $Handler = new class ($file) extends LogHandlerV3 {
            public function __construct(private readonly string $file)
            {
                parent::__construct();
            }

            protected function getLogFilePath(LogRecord $record): string
            {
                return $this->file;
            }
        };
        $Handler->setFormatter(new LineFormatter('%message%'));

        try {
            $Handler->handle(new LogRecord(
                datetime: new DateTimeImmutable(),
                channel: 'test',
                level: Level::Warning,
                message: 'first'
            ));
            $Handler->handle(new LogRecord(
                datetime: new DateTimeImmutable(),
                channel: 'test',
                level: Level::Warning,
                message: 'second'
            ));

            self::assertSame('firstsecond', file_get_contents($file));
        } finally {
            unlink($file);
        }
    }
}
