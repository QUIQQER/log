<?php

namespace QUI\Log;

use RuntimeException;

final class JavaScriptErrorLogger
{
    private const MAX_REPORTS_PER_PAGE = 20;
    private const AGGREGATION_DELAY = 1000;

    public static function getScript(): string
    {
        $script = file_get_contents(__DIR__ . '/logEvents.js');

        if ($script === false) {
            throw new RuntimeException('Cannot read the JavaScript error logger.');
        }

        $settings = [
            'ignoredMessages' => JavaScriptErrorFilter::IGNORED_MESSAGES,
            'maxReports' => self::MAX_REPORTS_PER_PAGE,
            'aggregationDelay' => self::AGGREGATION_DELAY
        ];
        $settingsJson = json_encode(
            $settings,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return '<script type="text/javascript">' . rtrim($script) . '(' . $settingsJson . ');</script>';
    }
}
