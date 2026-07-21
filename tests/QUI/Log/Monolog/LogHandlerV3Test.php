<?php

namespace QUI\Log\Tests\QUI\Log\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Log\Monolog\LogHandlerV3;
use RuntimeException;

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
        yield 'absolute path' => ['/tmp/outside', 'tmp-outside'];
        yield 'empty filename' => ['', null];
        yield 'current directory' => ['.', null];
        yield 'parent directory' => ['..', null];
        yield 'whitespace' => ['custom log', 'custom-log'];
        yield 'unsafe characters' => ['custom:*?log', 'custom-log'];
        yield 'non-string value' => [['outside'], null];
    }

    #[DataProvider('customFilenameProvider')]
    public function testCustomFilenameValidation(mixed $filename, ?string $expected): void
    {
        $Handler = new class () extends LogHandlerV3 {
            /**
             * @param array<string, mixed> $context
             */
            public function getCustomFilenameForTest(array $context): ?string
            {
                return $this->getCustomFilename($context);
            }
        };

        self::assertSame($expected, $Handler->getCustomFilenameForTest(['filename' => $filename]));
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
            context: ['filename' => 'auth']
        );

        self::assertSame('auth-2030-01-02', $Handler->getLogFilenameForTest($record));
    }

    public function testExceptionContextIsNormalized(): void
    {
        $Handler = new class () extends LogHandlerV3 {
            /**
             * @param array<string, mixed> $context
             * @return array<array-key, mixed>
             */
            public function normalizeContextForTest(array $context): array
            {
                return $this->normalizeContext($context);
            }
        };

        $context = $Handler->normalizeContextForTest([
            'exception' => new RuntimeException('Something failed', 42)
        ]);

        self::assertSame(
            [
                'class' => RuntimeException::class,
                'message' => 'Something failed',
                'code' => 42
            ],
            array_intersect_key($context['exception'], array_flip(['class', 'message', 'code']))
        );
    }
}
