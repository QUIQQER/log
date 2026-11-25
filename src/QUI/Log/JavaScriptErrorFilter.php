<?php

namespace QUI\Log;

final class JavaScriptErrorFilter
{
    /**
     * Known browser-injected errors. Keep signatures specific to avoid hiding application errors.
     *
     * @var list<string>
     */
    public const IGNORED_MESSAGES = [
        "undefined is not an object (evaluating 'window.ethereum.selectedAddress = undefined')"
    ];

    /** @var list<string> */
    private const SEARCH_ENGINES = [
        'BingPreview',
        'bingbot',
        'compatible; Googlebot',
        'AdsBot-Google-Mobile'
    ];

    /** @var list<string> */
    private const SEARCH_ENGINE_SCRIPT_URLS = [
        'require.js',
        'image.min.js'
    ];

    /** @var list<string> */
    private const IGNORED_SCRIPT_URLS = [
        'css.min.js'
    ];

    public static function shouldIgnore(string $message, string $url, string $browser): bool
    {
        foreach (self::IGNORED_MESSAGES as $signature) {
            if (str_contains($message, $signature)) {
                return true;
            }
        }

        foreach (self::IGNORED_SCRIPT_URLS as $scriptUrl) {
            if (str_contains($url, $scriptUrl)) {
                return true;
            }
        }

        foreach (self::SEARCH_ENGINES as $searchEngine) {
            if (!str_contains($browser, $searchEngine)) {
                continue;
            }

            if ($url === '') {
                return true;
            }

            foreach (self::SEARCH_ENGINE_SCRIPT_URLS as $scriptUrl) {
                if (str_contains($url, $scriptUrl)) {
                    return true;
                }
            }
        }

        return false;
    }
}
