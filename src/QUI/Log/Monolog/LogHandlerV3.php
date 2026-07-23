<?php

namespace QUI\Log\Monolog;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use QUI;
use QUI\Utils\Security\Orthos;
use UnexpectedValueException;

use const FILE_APPEND;
use const LOCK_EX;

class LogHandlerV3 extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $file = $this->getLogFilePath($record);
        $logEntry = (string)$record->formatted;
        $bytesWritten = file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);

        if ($bytesWritten === false || $bytesWritten !== strlen($logEntry)) {
            throw new UnexpectedValueException(sprintf('Could not write log record to "%s".', $file));
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new QuiqqerFileFormatter();
    }

    protected function getLogFilename(LogRecord $record): string
    {
        $customFilename = $this->getCustomFilename($record);
        $filename = $customFilename ?? QUI\System\Log::levelToLogName($record->level->value);

        return $filename . $record->datetime->format('-Y-m-d');
    }

    protected function getLogFilePath(LogRecord $record): string
    {
        $filename = $this->getLogFilename($record);

        $dir = VAR_DIR . 'log/';
        $file = $dir . $filename . '.log';

        QUI\Utils\System\File::mkdir($dir);

        return $file;
    }

    /**
     * Read the legacy context value as a fallback for records that did not pass
     * through the QUIQQER metadata processor.
     */
    protected function getCustomFilename(LogRecord $record): ?string
    {
        $metadata = $record->extra['quiqqer'] ?? [];
        $filename = is_array($metadata) ? ($metadata['filename'] ?? null) : null;
        $filename ??= $record->context['filename'] ?? null;

        if (!is_string($filename)) {
            return null;
        }

        $filename = Orthos::clearFilename($filename);

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
