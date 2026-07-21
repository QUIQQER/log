<?php

namespace QUI\Log;

final class Config
{
    public static function isDebugLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('debug');
    }

    public static function isDeprecationLoggingEnabled(): bool
    {
        return !empty(\QUI::conf('globals', 'log_deprecated_errors'));
    }

    public static function isInfoLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('info');
    }

    public static function isNoticeLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('notice');
    }

    public static function isWarningLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('warning');
    }

    public static function isErrorLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('error');
    }

    public static function isCriticalLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('critical');
    }

    public static function isAlertLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('alert');
    }

    public static function isEmergencyLoggingEnabled(): bool
    {
        return self::isLoggingEnabled('emergency');
    }

    private static function isLoggingEnabled(string $logLevel): bool
    {
        return (bool)Logger::getPackage()->getConfig()?->get('log_levels', $logLevel);
    }

    public static function isAllEventLoggingEnabled(): bool
    {
        return (bool)Logger::getPackage()->getConfig()?->get('log', 'logAllEvents');
    }
}
