<?php

namespace QUI\Log;

use Monolog;
use QUI;
use QUI\Exception;
use QUI\System\Log;

use function class_exists;

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

        self::$Logger->pushHandler(new QUI\Log\Monolog\LogHandlerV3());

        self::configureGraylogIfEnabled(self::$Logger);
        self::configureChromePHPHandlerIfEnabled(self::$Logger);
        self::configureFirePHPHandlerIfEnabled(self::$Logger);
        self::configureBrowserPHPHandlerIfEnabled(self::$Logger);
        self::configureCubeHandlerIfEnabled(self::$Logger);
        self::configureRedisHandlerIfEnabled(self::$Logger);
        self::configureSyslogUDPHandlerIfEnabled(self::$Logger);

        try {
            QUI::getEvents()->fireEvent('quiqqerLogGetLogger', [self::$Logger]);
        } catch (\Exception $Exception) {
            self::$Logger->notice($Exception->getMessage());
        }
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
        self::configureGraylogIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use Graylog, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureGraylogIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $graylog = $Config?->get('graylog');

        if (!$graylog) {
            return;
        }

        $server = $Config->get('graylog', 'server');
        $port = $Config->get('graylog', 'port');

        if (empty($server) || empty($port)) {
            return;
        }

        if (!class_exists('Gelf\Publisher') || !class_exists('Gelf\Transport\TcpTransport')) {
            $Logger->info(
                '\Gelf\Publisher class is missing. Please install: "graylog2/gelf-php": "~1.2"'
            );

            return;
        }

        try {
            $Publisher = new \Gelf\Publisher(
                new \Gelf\Transport\TcpTransport(
                    $server,
                    $port
                )
            );

            // @phpstan-ignore-next-line
            $Handler = new Monolog\Handler\GelfHandler($Publisher);

            $Logger->pushHandler($Handler);
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
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
        self::configureChromePHPHandlerIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use ChromePHP, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureChromePHPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $browser = $Config?->get('browser_logs');

        if (!$browser) {
            return;
        }

        $chromePHP = $Config->get('browser_logs', 'chromephp');
        $userLoggedIn = $Config->get('browser_logs', 'userLogedIn');

        if (empty($chromePHP)) {
            return;
        }

        if ($userLoggedIn && !QUI::getUserBySession()->getId()) {
            return;
        }

        try {
            $Logger->pushHandler(new Monolog\Handler\ChromePHPHandler());
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
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
        self::configureFirePHPHandlerIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use FirePHP, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureFirePHPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $browser = $Config?->get('browser_logs');

        if (!$browser) {
            return;
        }

        $firephp = $Config->get('browser_logs', 'firephp');
        $userLoggedIn = $Config->get('browser_logs', 'userLogedIn');

        if (empty($firephp)) {
            return;
        }

        if ($userLoggedIn && !QUI::getUserBySession()->getId()) {
            return;
        }

        try {
            $Logger->pushHandler(new Monolog\Handler\FirePHPHandler());
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
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
        self::configureBrowserPHPHandlerIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use BrowserPHP, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureBrowserPHPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $browser = $Config?->get('browser_logs');

        if (!$browser) {
            return;
        }

        $browserPHP = $Config->get('browser_logs', 'browserphp');
        $userLoggedIn = $Config->get('browser_logs', 'userLogedIn');

        if (empty($browserPHP)) {
            return;
        }

        if ($userLoggedIn && !QUI::getUserBySession()->getId()) {
            return;
        }

        try {
            $Logger->pushHandler(new Monolog\Handler\BrowserConsoleHandler());
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
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
        self::configureCubeHandlerIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use SyslogUDP, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureCubeHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $cube = $Config?->get('cube');

        if (!$cube) {
            return;
        }

        $server = $Config->get('cube', 'server');

        if (empty($server)) {
            return;
        }

        try {
            $Handler = new Monolog\Handler\CubeHandler($server);
            $Logger->pushHandler($Handler);
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
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
        self::configureRedisHandlerIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use Redis, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureRedisHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $redis = $Config?->get('redis');

        if (!$redis) {
            return;
        }

        $server = $Config->get('redis', 'server');

        if (empty($server)) {
            return;
        }

        if (!class_exists('Predis\Client')) {
            $Logger->info(
                '\Predis\Client class is missing.'
            );

            return;
        }

        try {
            $Client = new \Predis\Client($server);

            $Handler = new Monolog\Handler\RedisHandler(
                $Client,
                $server
            );

            $Logger->pushHandler($Handler);
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
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
        self::configureSyslogUDPHandlerIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use SyslogUDP, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureSyslogUDPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $syslog = $Config?->get('syslogUdp');

        if (!$syslog) {
            return;
        }

        $host = $Config->get('syslogUdp', 'host');
        $port = $Config->get('syslogUdp', 'port');

        if (empty($host)) {
            return;
        }


        try {
            $Handler = new Monolog\Handler\SyslogUdpHandler($host, $port);
            $Logger->pushHandler($Handler);
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
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
        self::configureNewRelicIfEnabled($Logger);
    }

    /**
     * Configure the given logger to use NewRelic, if settings and dependencies are available
     *
     * @throws Exception
     */
    private static function configureNewRelicIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = self::getPackage()->getConfig();
        $newRelic = $Config?->get('newRelic');

        if (!$newRelic) {
            return;
        }

        $appName = $Config->get('newRelic', 'appname');

        if (empty($appName)) {
            return;
        }

        try {
            $Handler = new Monolog\Handler\NewRelicHandler(
                Log::LEVEL_INFO,
                true,
                $appName
            );

            $Logger->pushHandler($Handler);
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
    }

    private static function configureExceptionHandling(): void
    {
        set_exception_handler(exception_handler(...));
    }
}
