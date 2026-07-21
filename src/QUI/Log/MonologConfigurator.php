<?php

namespace QUI\Log;

use Monolog;
use QUI;
use QUI\Exception;
use QUI\System\Log;

use function class_exists;

final class MonologConfigurator
{
    /**
     * Configure the given logger to use the QUIQQER log handler
     *
     * @internal
     */
    public static function configureQuiqqerLogging(Monolog\Logger $Logger): void
    {
        $Logger->pushHandler(new QUI\Log\Monolog\LogHandlerV3());
    }

    /**
     * Configure the given logger to use Graylog, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureGraylogIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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
     * Configure the given logger to use ChromePHP, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureChromePHPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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
     * Configure the given logger to use FirePHP, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureFirePHPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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
     * Configure the given logger to use BrowserPHP, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureBrowserPHPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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
     * Configure the given logger to use Cube, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureCubeHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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
     * Configure the given logger to use Redis, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureRedisHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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
     * Configure the given logger to use SyslogUDP, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureSyslogUDPHandlerIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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

    /**
     * Configure the given logger to use NewRelic, if settings and dependencies are available
     *
     * @internal
     * @throws Exception
     */
    public static function configureNewRelicIfEnabled(Monolog\Logger $Logger): void
    {
        $Config = Logger::getPackage()->getConfig();
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
}
