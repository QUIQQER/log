<?php

/**
 * This file contains QUI\Log\Logger class
 */

namespace QUI\Log;

use Gelf\Publisher;
use Gelf\Transport\TcpTransport;
use Monolog;
use Predis\Client;
use QUI;
use QUI\Exception;
use QUI\System\Log;

use function explode;

/**
 * QUIQQER logging
 *
 * @package quiqqer/log
 * @author  Henning Leutz (PCSG)
 * @licence For copyright and license information, please view the /README.md
 */
class Logger
{
    /**
     * Monolog Logger
     *
     * @var ?Monolog\Logger
     */
    public static ?Monolog\Logger $Logger = null;
    /**
     * which levels should be logged
     *
     * @var array
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

    /**
     * event on fire event
     * log all events?
     *
     * @param array|string $params
     */
    public static function logOnFireEvent(array|string $params): void
    {
        if (self::$logOnFireEvent === null) {
            self::$logOnFireEvent = 0;

            try {
                if (self::getPackage()->getConfig()->get('log', 'logAllEvents')) {
                    self::$logOnFireEvent = 1;
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
     * Return the quiqqer log plugins
     *
     * @return QUI\Package\Package
     * @throws Exception
     */
    public static function getPackage(): QUI\Package\Package
    {
        return QUI::getPackage('quiqqer/log');
    }

    /**
     * Return the Logger object
     *
     * @return Monolog\Logger|null
     * @throws Exception
     */
    public static function getLogger(): ?Monolog\Logger
    {
        if (self::$Logger) {
            return self::$Logger;
        }

        $Logger = new Monolog\Logger('QUI:Log');

        self::$Logger = $Logger;

        // which levels should be logged
        self::$logLevels = self::getPackage()->getConfig()->get('log_levels');

        // v2 or v3
        if (self::isMonologV2()) {
            $Logger->pushHandler(new QUI\Log\Monolog\LogHandlerV2());
        } else {
            $Logger->pushHandler(new QUI\Log\Monolog\LogHandlerV3());
        }

        self::addGraylogToLogger($Logger);
        self::addChromePHPHandlerToLogger($Logger);
        self::addFirePHPHandlerToLogger($Logger);
        self::addBrowserPHPHandlerToLogger($Logger);
        self::addCubeHandlerToLogger($Logger);
        self::addRedisHandlerToLogger($Logger);
        self::addSyslogUDPHandlerToLogger($Logger);

        try {
            QUI::getEvents()->fireEvent('quiqqerLogGetLogger', [$Logger]);
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }

        return $Logger;
    }

    public static function isMonologV2(): bool
    {
        if (self::$monologVersion === null) {
            $Monolog = QUI::getPackageManager()->getInstalledPackage('monolog/monolog');
            $lock = QUI::getPackageManager()->getPackageLock($Monolog);
            $version = explode('.', $lock['version'])[0];
            $version = (int)$version;

            self::$monologVersion = $version;
        }

        return self::$monologVersion === 2;
    }

    /**
     * Add a graylog handler to the logger, if settings are available
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addGraylogToLogger(Monolog\Logger $Logger): void
    {
        $graylog = self::getPackage()->getConfig()->get('graylog');

        if (!$graylog) {
            return;
        }

        $server = self::getPackage()->getConfig()->get('graylog', 'server');
        $port = self::getPackage()->getConfig()->get('graylog', 'port');

        if (empty($server) || empty($port)) {
            return;
        }


        if (!class_exists('\Gelf\Publisher')) {
            $Logger->info(
                '\Gelf\Publisher class is missing. Please install: "graylog2/gelf-php": "~1.2"'
            );

            return;
        }

        try {
            $Publisher = new Publisher(
                new TcpTransport(
                    self::getPackage()->getConfig()->get('graylog', 'server'),
                    self::getPackage()->getConfig()->get('graylog', 'port')
                )
            );

            $Handler = new Monolog\Handler\GelfHandler($Publisher);

            $Logger->pushHandler($Handler);
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
    }

    /**
     * Add a ChromePHP handler to the logger, if settings are available
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addChromePHPHandlerToLogger(Monolog\Logger $Logger): void
    {
        $browser = self::getPackage()->getConfig()->get('browser_logs');

        if (!$browser) {
            return;
        }

        $chromephp = self::getPackage()->getConfig()->get('browser_logs', 'chromephp');
        $userLogedIn = self::getPackage()->getConfig()->get('browser_logs', 'userLogedIn');

        if (empty($chromephp)) {
            return;
        }

        if ($userLogedIn && !QUI::getUserBySession()->getId()) {
            return;
        }

        try {
            $Logger->pushHandler(new Monolog\Handler\ChromePHPHandler());
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
    }

    /**
     * Handler
     */

    /**
     * Add a FirePHP handler to the logger, if settings are available
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addFirePHPHandlerToLogger(Monolog\Logger $Logger): void
    {
        $browser = self::getPackage()->getConfig()->get('browser_logs');

        if (!$browser) {
            return;
        }

        $firephp = self::getPackage()->getConfig()->get('browser_logs', 'firephp');
        $userLogedIn = self::getPackage()->getConfig()->get('browser_logs', 'userLogedIn');

        if (empty($firephp)) {
            return;
        }

        if ($userLogedIn && !QUI::getUserBySession()->getId()) {
            return;
        }

        try {
            $Logger->pushHandler(new Monolog\Handler\FirePHPHandler());
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());
        }
    }

    /**
     * Add a Browser php handler to the logger, if settings are available
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addBrowserPHPHandlerToLogger(Monolog\Logger $Logger): void
    {
        $browser = self::getPackage()->getConfig()->get('browser_logs');

        if (!$browser) {
            return;
        }

        $browserPHP = self::getPackage()->getConfig()->get('browser_logs', 'browserphp');
        $userLoggedIn = self::getPackage()->getConfig()->get('browser_logs', 'userLogedIn');

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
     * Add a Cube handler to the logger, if settings are available
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addCubeHandlerToLogger(Monolog\Logger $Logger): void
    {
        $cube = self::getPackage()->getConfig()->get('cube');

        if (!$cube) {
            return;
        }

        $server = self::getPackage()->getConfig()->get('cube', 'server');

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
     * Add a Redis handler to the logger, if settings are available
     *
     * @needle predis/predis
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addRedisHandlerToLogger(Monolog\Logger $Logger): void
    {
        $redis = self::getPackage()->getConfig()->get('redis');

        if (!$redis) {
            return;
        }

        $server = self::getPackage()->getConfig()->get('redis', 'server');

        if (empty($server)) {
            return;
        }

        try {
            $Client = new Client($server);

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
     * Add a SystelogUPD handler to the logger, if settings are available
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addSyslogUDPHandlerToLogger(Monolog\Logger $Logger): void
    {
        $syslog = self::getPackage()->getConfig()->get('syslogUdp');

        if (!$syslog) {
            return;
        }

        $host = self::getPackage()->getConfig()->get('syslogUdp', 'host');
        $port = self::getPackage()->getConfig()->get('syslogUdp', 'port');

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

    /**
     * event : on header loaded -> set error reporting
     */
    public static function onHeaderLoaded(): void
    {
        if (self::$logLevels['debug'] || DEVELOPMENT == 1) {
            error_reporting(E_ALL);

            if (DEVELOPMENT == 1) {
                error_reporting(E_ALL | E_DEPRECATED);
            }

            return;
        }

        $errorLevel = E_ERROR;

        if (self::$logLevels['warning']) {
            $errorLevel = $errorLevel | E_WARNING;
        }

        if (
            self::$logLevels['error']
            || self::$logLevels['critical']
            || self::$logLevels['alert']
        ) {
            $errorLevel = $errorLevel | E_PARSE;
        }

        if (self::$logLevels['notice']) {
            $errorLevel = $errorLevel | E_NOTICE;
        }

        if (self::$logLevels['error']) {
            $errorLevel = $errorLevel | E_CORE_ERROR;
        }

        if (self::$logLevels['warning']) {
            $errorLevel = $errorLevel | E_CORE_WARNING;
        }

        if (self::$logLevels['error']) {
            $errorLevel = $errorLevel | E_COMPILE_ERROR;
        }

        if (self::$logLevels['warning']) {
            $errorLevel = $errorLevel | E_COMPILE_WARNING;
        }

        if (self::$logLevels['error']) {
            $errorLevel = $errorLevel | E_USER_ERROR;
        }

        if (self::$logLevels['warning']) {
            $errorLevel = $errorLevel | E_USER_WARNING;
        }

        if (self::$logLevels['notice']) {
            $errorLevel = $errorLevel | E_USER_NOTICE;
        }

        if (self::$logLevels['info']) {
            $errorLevel = $errorLevel | E_STRICT;
        }

        if (self::$logLevels['error']) {
            $errorLevel = $errorLevel | E_RECOVERABLE_ERROR;
        }

        error_reporting($errorLevel);
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
     * Add a NewRelic handler to the logger, if settings are available
     *
     * @param Monolog\Logger $Logger
     * @throws Exception
     */
    public static function addNewRelicToLogger(Monolog\Logger $Logger): void
    {
        $newRelic = self::getPackage()->getConfig()->get('newRelic');

        if (!$newRelic) {
            return;
        }

        $appName = self::getPackage()->getConfig()->get('newRelic', 'appname');

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
}
