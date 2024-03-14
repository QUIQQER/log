<?php

/**
 * Log a javascript error
 *
 * @param string $errMsg
 * @param string $errUrl
 * @param integer|String $errLinenumber
 * @param string $browser - Browser String
 */
function package_quiqqer_log_ajax_logJsError(
    $errMsg,
    $errUrl,
    $errLinenumber,
    $browser,
    $context
) {
    $User = QUI::getUserBySession();

    if (!empty($context)) {
        $context = json_decode($context, true);
    }

    $isSearchEngine = function () use ($browser) {
        if (str_contains($browser, 'BingPreview')) {
            return true;
        }

        if (
            str_contains($browser, 'compatible; Googlebot')
            || str_contains($browser, 'AdsBot-Google-Mobile')
        ) {
            return true;
        }

        return false;
    };

    // don't log require.js error logs from search engines, search previews
    if (str_contains($errUrl, 'require.js') && $isSearchEngine()) {
        return;
    }

    // don't log require.js css min error logs from search engines, search previews
    if (str_contains($errUrl, 'css.min.js') && $isSearchEngine()) {
        return;
    }

    $error = "\n";
    $error .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
    $error .= "File: {$errUrl}\n";
    $error .= "Line Number: {$errLinenumber}\n";
    $error .= "Error: {$errMsg}\n";
    $error .= "Browser: {$browser}\n";
    $error .= "\n";
    $error .= "Username: {$User->getName()}\n";

    if (!empty($context)) {
        $error .= "Context:" . PHP_EOL;
        $error .= print_r($context, true);
    }

    $error .= "\n================================\n";

    QUI\System\Log::addError($error, [], 'js_errors');
}

QUI::$Ajax->register(
    'package_quiqqer_log_ajax_logJsError',
    ['errMsg', 'errUrl', 'errLinenumber', 'browser', 'context']
);
