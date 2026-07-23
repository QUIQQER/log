<?php

namespace QUI\Log\Monolog;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;
use Monolog\Utils;

use const JSON_PRETTY_PRINT;

final class QuiqqerFileFormatter extends NormalizerFormatter
{
    public function format(LogRecord $record): string
    {
        $metadata = [];
        $context = $this->normalizeValue($record->context);
        $extra = $this->normalizeValue($record->extra);

        if (is_array($context) && $context !== []) {
            $metadata['context'] = $context;
        }

        if (is_array($extra) && $extra !== []) {
            $metadata['extra'] = $extra;
        }

        $logEntry = sprintf(
            '[%s] %s: %s',
            $record->datetime->format('Y-m-d\TH:i:s.uP'),
            $record->level->getName(),
            $record->message
        );

        if ($metadata !== []) {
            $logEntry .= PHP_EOL . Utils::jsonEncode(
                $metadata,
                Utils::DEFAULT_JSON_FLAGS | JSON_PRETTY_PRINT
            );
        }

        return $logEntry . PHP_EOL;
    }
}
