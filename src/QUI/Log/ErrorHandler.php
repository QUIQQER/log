<?php

namespace QUI\Log;

use QUI;
use QUI\System\Log;
use Throwable;

/**
 * @internal
 */
final class ErrorHandler
{
    public static function attach(): void
    {
        self::configureErrorHandling();
        self::configureExceptionHandling();
    }

    private static function configureErrorHandling(): void
    {
        ini_set("error_log", VAR_DIR . 'log/error' . date('-Y-m-d') . '.log');

        $errorReportingLevel = self::getPhpErrorReportingLevel();
        error_reporting($errorReportingLevel);

        set_error_handler(self::handleError(...), $errorReportingLevel);
    }

    private static function getPhpErrorReportingLevel(): int
    {
        if (DEBUG_MODE === true) {
            return E_ALL;
        }

        $errorReportingLevel = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED;

        // enable deprecation logging if in delevopment mode or explicitly enabled
        if (DEVELOPMENT || Config::isDeprecationLoggingEnabled()) {
            $errorReportingLevel = $errorReportingLevel | E_DEPRECATED;
            $errorReportingLevel = $errorReportingLevel | E_USER_DEPRECATED;
        }

        if (
            !Config::isEmergencyLoggingEnabled() &&
            !Config::isAlertLoggingEnabled() &&
            !Config::isCriticalLoggingEnabled() &&
            !Config::isErrorLoggingEnabled()
        ) {
            $errorReportingLevel = $errorReportingLevel & ~E_PARSE;
        }

        if (!Config::isErrorLoggingEnabled()) {
            $errorReportingLevel = $errorReportingLevel
                & ~E_ERROR
                & ~E_CORE_ERROR
                & ~E_COMPILE_ERROR
                & ~E_USER_ERROR
                & ~E_RECOVERABLE_ERROR;
        }

        if (!Config::isWarningLoggingEnabled()) {
            $errorReportingLevel = $errorReportingLevel
                & ~E_WARNING
                & ~E_USER_WARNING
                & ~E_CORE_WARNING
                & ~E_COMPILE_WARNING;
        }

        if (!Config::isNoticeLoggingEnabled()) {
            $errorReportingLevel = $errorReportingLevel & ~E_NOTICE & ~E_USER_NOTICE & ~@E_STRICT;
        }

        return $errorReportingLevel;
    }

    private static function configureExceptionHandling(): void
    {
        set_exception_handler(self::handleUncaughtException(...));
    }

    public static function handleError(
        int $errorLevel,
        string $errorMessage,
        string $errorFile,
        int $errorLine
    ): bool {
        if ($errorMessage === 'json_encode(): Invalid UTF-8 sequence in argument') {
            QUI::getErrorHandler()->setAttribute('show_request', true);
            QUI::getErrorHandler()->writeErrorToLog($errorLevel, $errorMessage, $errorFile, $errorLine);
            QUI::getErrorHandler()->setAttribute('show_request', false);

            return true;
        }

        if (
            str_contains($errorMessage, 'session_regenerate_id()')
            || str_contains($errorMessage, 'session_destroy()')
            || str_contains($errorMessage, 'Required parameter $permissions follows optional parameter $path')
        ) {
            return true;
        }

        $context = [
            'file' => $errorFile,
            'line' => $errorLine
        ];

        $loggingMethod = match ($errorLevel) {
            E_DEPRECATED, E_USER_DEPRECATED => Log::addDeprecated(...),
            E_NOTICE, E_USER_NOTICE, E_STRICT => Log::addNotice(...),
            E_WARNING, E_USER_WARNING => Log::addWarning(...),
            E_RECOVERABLE_ERROR, E_USER_ERROR => Log::addError(...),
            default => Log::addError(...),
        };

        $loggingMethod($errorMessage, $context);

        if ($errorLevel === E_USER_ERROR) {
            if (php_sapi_name() === 'cli') {
                fwrite(
                    STDERR,
                    'Error: ' . $errorMessage . PHP_EOL
                    . 'File: ' . $errorFile . PHP_EOL
                    . 'Line: ' . $errorLine . PHP_EOL
                );
            }

            exit(1);
        }

        return true;
    }

    public static function handleUncaughtException(Throwable $Exception): void
    {
        $isCacheMissException = $Exception instanceof QUI\Cache\MissException;

        if (!$isCacheMissException) {
            Log::writeException($Exception);
        }

        if (php_sapi_name() === 'cli') {
            $message =
                'Uncaught Exception: ' . $Exception->getMessage() . PHP_EOL
                . 'File: ' . $Exception->getFile() . PHP_EOL
                . 'Line: ' . $Exception->getLine() . PHP_EOL;

            if (!$isCacheMissException) {
                $message .= 'Further details were written to the error log.' . PHP_EOL;
            }

            fwrite(STDERR, $message);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: application/json');
        }

        echo json_encode([
            'error' => true,
            'message' => 'An error occurred. Check the log for more details.',
            'code' => $Exception->getCode()
        ]);
    }
}
