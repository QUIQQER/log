<?php

namespace QUI\Log\Tests\QUI\Log;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Log\JavaScriptErrorFilter;
use QUI\Log\JavaScriptErrorLogger;

class JavaScriptErrorFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function errors(): iterable
    {
        $wallet = "TypeError: undefined is not an object (evaluating 'window.ethereum.selectedAddress = undefined')";

        yield 'known injected wallet error' => [$wallet, '/product', 'Safari Brave', true];
        yield 'wallet error without Brave in user agent' => [$wallet, '/product', 'Safari', true];
        yield 'wallet error with Uncaught prefix' => ['Uncaught ' . $wallet, '/product', 'Brave', true];
        yield 'different Ethereum error' => ['Cannot read window.ethereum', '/checkout.js', 'Brave', false];
        yield 'generic script error' => ['Script error.', '', 'Brave', false];
        yield 'require module error' => ['Script error for "OrderProcessUrl"', '/cache.js', 'Safari', false];
        yield 'require for normal browser' => ['Error', '/require.js', 'Safari', false];
        yield 'require for Bing preview' => ['Error', '/require.js', 'BingPreview', true];
        yield 'require for Bing crawler' => ['Error', '/require.js', 'bingbot', true];
        yield 'require for Google crawler' => ['Error', '/require.js', 'compatible; Googlebot', true];
        yield 'image for Google mobile ads' => ['Error', '/image.min.js', 'AdsBot-Google-Mobile', true];
        yield 'empty URL for crawler' => ['Script error.', '', 'bingbot', true];
        yield 'application error for crawler' => ['Error', '/basket.js', 'bingbot', false];
        yield 'CSS loader' => ['Error', '/css.min.js', 'Safari', true];
    }

    #[DataProvider('errors')]
    public function testOnlyKnownErrorsAreIgnored(
        string $message,
        string $url,
        string $browser,
        bool $expected
    ): void {
        self::assertSame($expected, JavaScriptErrorFilter::shouldIgnore($message, $url, $browser));
    }

    public function testSharedScriptUsesTheCentralFilterList(): void
    {
        $script = JavaScriptErrorLogger::getScript();
        self::assertStringStartsWith('<script type="text/javascript">', $script);
        self::assertStringEndsWith(');</script>', $script);

        $settingsStart = strrpos($script, ')(');
        self::assertNotFalse($settingsStart);
        $settingsJson = substr($script, $settingsStart + 2, -strlen(');</script>'));
        $settings = json_decode($settingsJson, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(JavaScriptErrorFilter::IGNORED_MESSAGES, $settings['ignoredMessages']);
        self::assertSame(20, $settings['maxReports']);
        self::assertSame(1000, $settings['aggregationDelay']);
    }
}
