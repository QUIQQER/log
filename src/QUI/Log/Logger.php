<?php

namespace QUI\Log;

use Monolog;
use QUI;
use QUI\Exception;
use QUI\System\Log;

class Logger
{
    public static Monolog\Logger $Logger;

    /**
     * which levels should be logged
     *
     * @var array<string, bool>
     */
    public static array $logLevels = [
        'debug' => true,
        'deprecated' => true,
        'info' => true,
        'notice' => true,
        'warning' => true,
        'error' => true,
        'critical' => true,
        'alert' => true,
        'emergency' => true
    ];
    /**
     * log events?
     *
     * @var boolean|null
     */
    protected static ?bool $logOnFireEvent = null;

    protected static ?int $monologVersion = null;

    private static function initialize(): void
    {
        $logLevels = self::getPackage()->getConfig()?->get('log_levels');

        if (is_array($logLevels)) {
            self::$logLevels = $logLevels;
        }

        self::$Logger = new Monolog\Logger('QUI:Log');

        self::configureMonolog(self::$Logger);

        try {
            QUI::getEvents()->fireEvent('quiqqerLogGetLogger', [self::$Logger]);
        } catch (\Exception $Exception) {
            self::$Logger->notice($Exception->getMessage());
        }
    }

    private static function configureMonolog(Monolog\Logger $monolog): void
    {
        MonologConfigurator::configureQuiqqerLogging($monolog);
        MonologConfigurator::configureGraylogIfEnabled($monolog);
        MonologConfigurator::configureChromePHPHandlerIfEnabled($monolog);
        MonologConfigurator::configureFirePHPHandlerIfEnabled($monolog);
        MonologConfigurator::configureBrowserPHPHandlerIfEnabled($monolog);
        MonologConfigurator::configureCubeHandlerIfEnabled($monolog);
        MonologConfigurator::configureRedisHandlerIfEnabled($monolog);
        MonologConfigurator::configureSyslogUDPHandlerIfEnabled($monolog);
    }

    /**
     * event on fire event
     * log all events?
     *
     * @param array<string, mixed>|string $params
     */
    public static function logOnFireEvent(array | string $params): void
    {
        if (self::$logOnFireEvent === null) {
            self::$logOnFireEvent = false;

            try {
                if (self::getPackage()->getConfig()?->get('log', 'logAllEvents')) {
                    self::$logOnFireEvent = true;
                }
            } catch (\Exception) {
            }
        }

        if (!self::$logOnFireEvent) {
            return;
        }

        $arguments = func_get_args();

        if (isset($arguments[0]['event']) && $arguments[0]['event'] == 'userLoad') {
            return;
        }

        if ($arguments[0] == 'userLoad') {
            return;
        }

        $Logger = self::getLogger();

        $User = QUI::getUserBySession();

        $context = [
            'username' => $User->getName(),
            'uid' => $User->getId(),
            'arguments' => $arguments
        ];

        $arguments = func_get_args();
        $event = $arguments[0]['event'] ?? $arguments[0];

        $Logger->info('event log ' . $event, $context);
    }

    /**
     * @throws Exception
     */
    public static function getPackage(): QUI\Package\Package
    {
        return QUI::getPackage('quiqqer/log');
    }

    /**
     * @throws Exception
     */
    public static function getLogger(): Monolog\Logger
    {
        if (!isset(self::$Logger)) {
            self::initialize();
        }

        return self::$Logger;
    }

    /**
     * Add a graylog handler to the logger, if settings and dependencies are available
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addGraylogToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureGraylogIfEnabled($Logger);
    }

    /**
     * Add a ChromePHP handler to the logger, if settings and dependencies are available
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addChromePHPHandlerToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureChromePHPHandlerIfEnabled($Logger);
    }

    /**
     * Add a FirePHP handler to the logger, if settings and dependencies are available
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addFirePHPHandlerToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureFirePHPHandlerIfEnabled($Logger);
    }

    /**
     * Add a Browser php handler to the logger, if settings and dependencies are available
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addBrowserPHPHandlerToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureBrowserPHPHandlerIfEnabled($Logger);
    }

    /**
     * Add a Cube handler to the logger, if settings and dependencies are available
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addCubeHandlerToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureCubeHandlerIfEnabled($Logger);
    }

    /**
     * Add a Redis handler to the logger, if settings and dependencies are available
     *
     * @needle predis/predis
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addRedisHandlerToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureRedisHandlerIfEnabled($Logger);
    }

    /**
     * Add a SystelogUPD handler to the logger, if settings and dependencies are available
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addSyslogUDPHandlerToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureSyslogUDPHandlerIfEnabled($Logger);
    }

    public static function onQuiqqerInit(): void
    {
        self::initialize();

        self::configureErrorHandling();
        self::configureExceptionHandling();
    }

    public static function onHeaderLoaded(): void
    {
        // This method has to be kept for backwards compatibility:
        // Removing it makes the QUIQQER event manager write to a log
        // …which instantiates the Logger
        // …which instantiates the Package Manager
        // …which instantiates the QUIQQER event manager
        // …which tries to write to a log
        // …which results in an infinite loop
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

        $explicitlyLogDeprecatedErrors = !empty(QUI::conf('globals', 'log_deprecated_errors'));

        // enable deprecation logging if in delevopment mode or explicitly enabled
        if (DEVELOPMENT || $explicitlyLogDeprecatedErrors) {
            $errorReportingLevel = $errorReportingLevel | E_DEPRECATED;
            $errorReportingLevel = $errorReportingLevel | E_USER_DEPRECATED;
        }

        if (
            self::$logLevels['emergency'] === false &&
            self::$logLevels['alert'] === false &&
            self::$logLevels['critical'] === false &&
            self::$logLevels['error'] === false
        ) {
            $errorReportingLevel = $errorReportingLevel & ~E_PARSE;
        }

        if (self::$logLevels['error'] === false) {
            $errorReportingLevel = $errorReportingLevel
                & ~E_ERROR
                & ~E_CORE_ERROR
                & ~E_COMPILE_ERROR
                & ~E_USER_ERROR
                & ~E_RECOVERABLE_ERROR;
        }

        if (self::$logLevels['warning'] == false) {
            $errorReportingLevel = $errorReportingLevel
                & ~E_WARNING
                & ~E_USER_WARNING
                & ~E_CORE_WARNING
                & ~E_COMPILE_WARNING;
        }

        if (self::$logLevels['notice'] == false) {
            $errorReportingLevel = $errorReportingLevel & ~E_NOTICE & ~E_USER_NOTICE & ~@E_STRICT;
        }

        return $errorReportingLevel;
    }

    /**
     * Write a message to the logger
     * event: onLogWrite
     *
     * @todo Remove this method in the next major version
     * @deprecated Use {@see Log::write()} instead
     *
     * @param string $message - Log message
     * @param integer $loglevel - Log::LEVEL_*
     * @throws Exception
     */
    public static function write(string $message, int $loglevel = Log::LEVEL_INFO): void
    {
        $Logger = self::getLogger();

        $User = QUI::getUserBySession();

        $context = [
            'username' => $User->getName(),
            'uid' => $User->getId()
        ];

        switch ($loglevel) {
            case Log::LEVEL_DEBUG:
                if (self::$logLevels['debug']) {
                    $Logger->debug($message, $context);
                }
                break;

            case Log::LEVEL_INFO:
                if (self::$logLevels['info']) {
                    $Logger->info($message, $context);
                }
                break;

            case Log::LEVEL_NOTICE:
                if (self::$logLevels['notice']) {
                    $Logger->notice($message, $context);
                }
                break;

            case Log::LEVEL_WARNING:
                if (self::$logLevels['warning']) {
                    $Logger->warning($message, $context);
                }
                break;

            case Log::LEVEL_ERROR:
                if (self::$logLevels['error']) {
                    $Logger->error($message, $context);
                }
                break;

            case Log::LEVEL_CRITICAL:
                if (self::$logLevels['critical']) {
                    $Logger->critical($message, $context);
                }
                break;

            case Log::LEVEL_ALERT:
                if (self::$logLevels['alert']) {
                    $Logger->alert($message, $context);
                }
                break;

            case Log::LEVEL_EMERGENCY:
                if (self::$logLevels['emergency']) {
                    $Logger->emergency($message, $context);
                }
                break;
        }
    }

    /**
     * Add a NewRelic handler to the logger, if settings and dependencies are available
     *
     * @deprecated This method is unintentionally public and will become private in the future
     * @todo Remove this method in the next major version
     *
     * @throws Exception
     */
    public static function addNewRelicToLogger(Monolog\Logger $Logger): void
    {
        MonologConfigurator::configureNewRelicIfEnabled($Logger);
    }

    private static function configureExceptionHandling(): void
    {
        set_exception_handler(exception_handler(...));
    }
}
