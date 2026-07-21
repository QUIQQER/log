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

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $filename = 'debug';
        } elseif (defined('DEVELOPMENT') && DEVELOPMENT) {
            $filename = 'dev';
        } else {
            $customFilename = $this->getCustomFilename($record->context);
            $filename = $customFilename ?? QUI\System\Log::levelToLogName($record->level->value);
            $filename .= date('-Y-m-d');
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

    /**
     * @param array<string, mixed> $context
     */
    protected function getCustomFilename(array $context): ?string
    {
        $filename = $context['filename'] ?? null;

        if (!is_string($filename)) {
            return null;
        }

        $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename);

        if ($filename === null) {
            return null;
        }

        $filename = trim($filename, '.-_');

        return $filename !== '' ? $filename : null;
    }
}
