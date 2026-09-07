<?php

namespace QUI\Log\Tests\QUI\Log;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Log\ErrorResponseFormat;
use Symfony\Component\HttpFoundation\Request;

class ErrorResponseFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool, bool, ?array{basePath: string, baseHost: string}, string}>
     */
    public static function requests(): iterable
    {
        $rest = ['basePath' => '/api/', 'baseHost' => ''];
        $restHost = ['basePath' => '/', 'baseHost' => 'https://api.example.com/'];

        yield 'browser page' => ['/', 'text/html', true, false, null, ''];
        yield 'default page' => ['/', '*/*', true, false, null, ''];
        yield 'JSON client' => ['/', 'application/json', false, false, null, ''];
        yield 'JSON preferred over HTML' => ['/', 'text/html;q=0.5,application/json', false, false, null, ''];
        yield 'Ajax flag' => ['/', 'text/html', false, true, null, ''];
        yield 'Ajax before bootstrap' => ['/ajax.php', 'text/html', false, false, null, ''];
        yield 'Ajax bundler in subdirectory' => ['/cms/ajaxBundler.php', 'text/html', false, false, null, ''];
        yield 'JSON response' => ['/', 'text/html', false, false, null, 'application/json'];
        yield 'problem JSON response' => ['/', 'text/html', false, false, null, 'application/problem+json'];
        yield 'HTML response' => ['/', 'text/html', true, false, null, 'text/html; charset=UTF-8'];
        yield 'REST base' => ['/api', 'text/html', false, false, $rest, ''];
        yield 'REST trailing slash' => ['/api/', 'text/html', false, false, $rest, ''];
        yield 'REST resource' => ['/api/items?limit=1', 'text/html', false, false, $rest, ''];
        yield 'unrelated prefix' => ['/api-docs', 'text/html', true, false, $rest, ''];
        yield 'page with REST installed' => ['/', 'text/html', true, false, $rest, ''];
        yield 'REST domain' => ['https://api.example.com/items', 'text/html', false, false, $restHost, ''];
        yield 'separate page domain' => ['https://www.example.com/', 'text/html', true, false, $restHost, ''];
        yield 'all paths are REST' => ['/items', 'text/html', false, false, ['basePath' => '', 'baseHost' => ''], ''];
        yield 'MCP endpoint' => ['/mcp', 'text/html', false, false, null, ''];
        yield 'MCP trailing slash' => ['/mcp/', 'text/html', false, false, null, ''];
        yield 'MCP metadata' => ['/.well-known/oauth-protected-resource/mcp', 'text/html', false, false, null, ''];
    }

    /**
     * @param array{basePath: string, baseHost: string}|null $restConfiguration
     */
    #[DataProvider('requests')]
    public function testResponseFormat(
        string $url,
        string $accept,
        bool $expected,
        bool $ajax,
        ?array $restConfiguration,
        string $responseContentType
    ): void {
        $Request = Request::create($url, server: ['HTTP_ACCEPT' => $accept]);

        self::assertSame($expected, ErrorResponseFormat::wantsHtml(
            $Request,
            $ajax,
            $restConfiguration,
            $responseContentType
        ));
    }

    public function testXmlHttpRequestKeepsJsonWithoutTheAjaxConstant(): void
    {
        $Request = Request::create('/', server: [
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);

        self::assertFalse(ErrorResponseFormat::wantsHtml($Request));
    }
}
