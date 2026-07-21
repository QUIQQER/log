<?php

namespace QUI\Log\Monolog;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Utils;
use QUI;

use const JSON_PRETTY_PRINT;

class LogHandlerV3 extends AbstractProcessingHandler
{
    private ?NormalizerFormatter $contextNormalizer = null;

    protected function write(LogRecord $record): void
    {
        $filename = $this->getLogFilename($record);

        $dir = VAR_DIR . 'log/';
        $file = $dir . $filename . '.log';

        QUI\Utils\System\File::mkdir($dir);

        $message = "\n[{$record->datetime->format('Y-m-d H:i:s')}] - " .
            "{$record->level->getName()} - " .
            $record->message;

        $message .= "\n" . Utils::jsonEncode(
            $this->normalizeContext($record->context),
            Utils::DEFAULT_JSON_FLAGS | JSON_PRETTY_PRINT
        ) . "\n";

        error_log($message, 3, $file);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<array-key, mixed>
     */
    protected function normalizeContext(array $context): array
    {
        $this->contextNormalizer ??= new NormalizerFormatter();
        $normalizedContext = $this->contextNormalizer->normalizeValue($context);

        return is_array($normalizedContext) ? $normalizedContext : [];
    }

    protected function getLogFilename(LogRecord $record): string
    {
        $customFilename = $this->getCustomFilename($record->context);
        $filename = $customFilename ?? QUI\System\Log::levelToLogName($record->level->value);

        return $filename . $record->datetime->format('-Y-m-d');
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
