<?php

namespace QUI\Log;

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

        set_error_handler(exception_error_handler(...), $errorReportingLevel);
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
        set_exception_handler(exception_handler(...));
    }
}
