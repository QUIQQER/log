<?php

namespace QUI\Log;

use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class ErrorResponseFormat
{
    /**
     * REST routes must keep JSON even when opened directly in a browser.
     *
     * @param array{basePath: string, baseHost: string}|null $restConfiguration
     */
    public static function wantsHtml(
        Request $Request,
        bool $ajax = false,
        ?array $restConfiguration = null,
        string $responseContentType = ''
    ): bool {
        if ($ajax || $Request->isXmlHttpRequest()) {
            return false;
        }

        $responseContentType = strtolower($responseContentType);

        if (str_contains($responseContentType, '/json') || str_contains($responseContentType, '+json')) {
            return false;
        }

        $path = '/' . trim((string)parse_url($Request->getRequestUri(), PHP_URL_PATH), '/');

        // These entrypoints can fail before they define QUIQQER_AJAX.
        if (in_array(basename($path), ['ajax.php', 'ajaxBundler.php'], true)) {
            return false;
        }

        if ($path === '/mcp' || $path === '/.well-known/oauth-protected-resource/mcp') {
            return false;
        }

        if ($restConfiguration !== null) {
            $host = rtrim(str_replace(['http://', 'https://'], '', $restConfiguration['baseHost']), '/');
            $basePath = '/' . trim($restConfiguration['basePath'], '/');

            if (
                ($host === '' || $host === $Request->getHost())
                && ($basePath === '/' || $path === $basePath || str_starts_with($path, $basePath . '/'))
            ) {
                return false;
            }
        }

        return $Request->getPreferredFormat('html') === 'html';
    }
}
