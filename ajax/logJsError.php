<?php

/**
 * This file contains package_quiqqer_log_ajax_logJsError
 */

/**
 * Log a javascript error
 *
 * @param string $errMsg
 * @param string $errUrl
 * @param integer|String $errLineNumber
 * @param string $browser - Browser String
 * @param array<string, mixed>|string|null $context
 * @param int|string $occurrences
 * @param int|string $errColumnNumber
 * @param string $errStack
 */
function package_quiqqer_log_ajax_logJsError(
    string $errMsg,
    string $errUrl,
    int | string $errLineNumber,
    string $browser,
    array|string|null $context,
    int|string $occurrences = 1,
    int|string $errColumnNumber = 0,
    string $errStack = ''
): void {
    if (QUI\Log\JavaScriptErrorFilter::shouldIgnore($errMsg, $errUrl, $browser)) {
        return;
    }

    $User = QUI::getUserBySession();

    if (is_string($context) && $context !== '') {
        $context = json_decode($context, true);
    }

    $error = "\n";
    $error .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
    $error .= "File: $errUrl\n";
    $error .= "Line Number: $errLineNumber\n";
    $error .= 'Column Number: ' . (int)$errColumnNumber . "\n";
    $error .= "Error: $errMsg\n";
    $error .= 'Occurrences: ' . max(1, (int)$occurrences) . "\n";
    $error .= "Browser: $browser\n";

    if ($errStack !== '') {
        $error .= "Stacktrace:\n$errStack\n";
    }
    $error .= "\n";
    $error .= "Username: {$User->getName()}\n";

    if (!empty($context)) {
        $error .= "Context:" . PHP_EOL;
        $error .= print_r($context, true);
    }

    $error .= "\n================================\n";

    QUI\System\Log::addError($error, [], 'js_errors');
}

QUI::getAjax()->register(
    'package_quiqqer_log_ajax_logJsError',
    [
        'errMsg',
        'errUrl',
        'errLineNumber',
        'browser',
        'context',
        'occurrences',
        'errColumnNumber',
        'errStack'
    ]
);
