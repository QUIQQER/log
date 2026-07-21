<?php

namespace QUI\Log\Tests\QUI\Log\Monolog;

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
}
