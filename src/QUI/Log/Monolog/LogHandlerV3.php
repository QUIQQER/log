<?php

/**
 * This file contains \QUI\Log\Monolog\LogHandler
 */

namespace QUI\Log\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use QUI;

use const JSON_PRETTY_PRINT;

/**
 * Class LogHandler
 */
class LogHandlerV3 extends AbstractProcessingHandler
{
    /**
     * @param LogRecord $record
     */
    protected function write(LogRecord $record): void
    {
//        $record['message'];
//        $record['context'];
//        $record['level'];
//        $record['level_name'];
//        $record['channel'];
//        $record['datetime'];
//        $record['extra'];
//        $record['formatted'];

        // @phpstan-ignore-next-line
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $filename = 'debug';
            // @phpstan-ignore-next-line
        } elseif (defined('DEVELOPMENT') && DEVELOPMENT) {
            $filename = 'dev';
        } elseif (
            $record->context
            && isset($record->context['filename'])
            && $record->context['filename']
        ) {
            $filename = $record->context['filename'] . date('-Y-m-d');
        } else {
            $filename = QUI\System\Log::levelToLogName($record->level->value) . date('-Y-m-d');
        }

        $dir = VAR_DIR . 'log/';
        $file = $dir . $filename . '.log';


        QUI\Utils\System\File::mkdir($dir);

        $message = "\n[{$record->datetime->format('Y-m-d H:i:s')}] - " .
            "{$record->level->getName()} - " .
            $record->message;

        $message .= "\n" . json_encode($record->context, JSON_PRETTY_PRINT) . "\n";

        error_log($message, 3, $file);
    }
}
