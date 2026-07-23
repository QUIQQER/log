<?php

namespace QUI\Log;

use Monolog;
use Monolog\Handler\HandlerInterface;
use QUI;
use QUI\Exception;
use QUI\System\Log;

use function class_exists;

final class MonologConfigurator
{
    /**
     * Configure the complete QUIQQER logging stack in processing order.
     *
     * @internal
     * @throws Exception
     */
    public static function configure(Monolog\Logger $Logger): void
    {
        $Logger->pushProcessor(new QUI\Log\Monolog\QuiqqerMetadataProcessor());
        $Handlers = [self::createQuiqqerHandler()];

        // Keep the local handler active while optional handlers are configured,
        // so configuration errors have a reliable destination.
        $Logger->setHandlers($Handlers);

        // Array filter removes "null" entries of unavailable loggers
        $OptionalHandlers = array_filter([
            self::createGraylogHandlerIfEnabled($Logger),
            self::createChromePHPHandlerIfEnabled($Logger),
            self::createFirePHPHandlerIfEnabled($Logger),
            self::createBrowserPHPHandlerIfEnabled($Logger),
            self::createCubeHandlerIfEnabled($Logger),
            self::createRedisHandlerIfEnabled($Logger),
            self::createSyslogUDPHandlerIfEnabled($Logger),
            self::createNewRelicHandlerIfEnabled($Logger)
        ]);

        $Logger->setHandlers($Handlers);
    }

    /**
     * Create the QUIQQER log handler
     *
     * @internal
     */
    public static function createQuiqqerHandler(): HandlerInterface
    {
        return new QUI\Log\Monolog\LogHandlerV3();
    }

    /**
     * Create a Graylog handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createGraylogHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $graylog = $Config?->get('graylog');

        if (!$graylog) {
            return null;
        }

        $server = $Config->get('graylog', 'server');
        $port = $Config->get('graylog', 'port');

        if (empty($server) || empty($port)) {
            return null;
        }

        if (!class_exists('Gelf\Publisher') || !class_exists('Gelf\Transport\TcpTransport')) {
            $Logger->info(
                '\Gelf\Publisher class is missing. Please install: "graylog2/gelf-php": "~1.2"'
            );

            return null;
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

            return $Handler;
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }

    /**
     * Create a ChromePHP handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createChromePHPHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $browser = $Config?->get('browser_logs');

        if (!$browser) {
            return null;
        }

        $chromePHP = $Config->get('browser_logs', 'chromephp');
        $userLoggedIn = $Config->get('browser_logs', 'userLogedIn');

        if (empty($chromePHP)) {
            return null;
        }

        if ($userLoggedIn && !QUI::getUserBySession()->getId()) {
            return null;
        }

        try {
            return new Monolog\Handler\ChromePHPHandler();
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }

    /**
     * Create a FirePHP handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createFirePHPHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $browser = $Config?->get('browser_logs');

        if (!$browser) {
            return null;
        }

        $firephp = $Config->get('browser_logs', 'firephp');
        $userLoggedIn = $Config->get('browser_logs', 'userLogedIn');

        if (empty($firephp)) {
            return null;
        }

        if ($userLoggedIn && !QUI::getUserBySession()->getId()) {
            return null;
        }

        try {
            return new Monolog\Handler\FirePHPHandler();
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }

    /**
     * Create a BrowserPHP handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createBrowserPHPHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $browser = $Config?->get('browser_logs');

        if (!$browser) {
            return null;
        }

        $browserPHP = $Config->get('browser_logs', 'browserphp');
        $userLoggedIn = $Config->get('browser_logs', 'userLogedIn');

        if (empty($browserPHP)) {
            return null;
        }

        if ($userLoggedIn && !QUI::getUserBySession()->getId()) {
            return null;
        }

        try {
            return new Monolog\Handler\BrowserConsoleHandler();
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }

    /**
     * Create a Cube handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createCubeHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $cube = $Config?->get('cube');

        if (!$cube) {
            return null;
        }

        $server = $Config->get('cube', 'server');

        if (empty($server)) {
            return null;
        }

        try {
            $Handler = new Monolog\Handler\CubeHandler($server);
            return $Handler;
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }

    /**
     * Create a Redis handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createRedisHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $redis = $Config?->get('redis');

        if (!$redis) {
            return null;
        }

        $server = $Config->get('redis', 'server');

        if (empty($server)) {
            return null;
        }

        if (!class_exists('Predis\Client')) {
            $Logger->info(
                '\Predis\Client class is missing.'
            );

            return null;
        }

        try {
            $Client = new \Predis\Client($server);

            $Handler = new Monolog\Handler\RedisHandler(
                $Client,
                $server
            );

            return $Handler;
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }

    /**
     * Create a SyslogUDP handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createSyslogUDPHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $syslog = $Config?->get('syslogUdp');

        if (!$syslog) {
            return null;
        }

        $host = $Config->get('syslogUdp', 'host');
        $port = $Config->get('syslogUdp', 'port');

        if (empty($host)) {
            return null;
        }

        try {
            $Handler = new Monolog\Handler\SyslogUdpHandler($host, $port);
            return $Handler;
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }

    /**
     * Create a NewRelic handler if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function createNewRelicHandlerIfEnabled(Monolog\Logger $Logger): ?HandlerInterface
    {
        $Config = Config::getPackageConfig();
        $newRelic = $Config?->get('newRelic');

        if (!$newRelic) {
            return null;
        }

        $appName = $Config->get('newRelic', 'appname');

        if (empty($appName)) {
            return null;
        }

        try {
            $Handler = new Monolog\Handler\NewRelicHandler(
                Log::LEVEL_INFO,
                true,
                $appName
            );

            return $Handler;
        } catch (\Exception $Exception) {
            $Logger->notice($Exception->getMessage());

            return null;
        }
    }
}
