<?php

namespace QUI\Log\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use QUI;

final class QuiqqerMetadataProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;
        $metadata = $extra['quiqqer'] ?? [];

        if (!is_array($metadata)) {
            $metadata = [];
        }

        if (array_key_exists('filename', $context)) {
            $metadata['filename'] = $context['filename'];
            unset($context['filename']);
        }

        if (!empty($_SERVER['REQUEST_URI']) && defined('HOST')) {
            $metadata['request'] = HOST . $_SERVER['REQUEST_URI'];
        }

        if (isset($_REQUEST['quiqqerBundle'])) {
            $metadata['ajaxBundler'] = $_REQUEST['quiqqerBundle'];
        }

        if ($clientIP = QUI\Utils\System::getClientIP()) {
            $metadata['ip'] = $clientIP;
        }

        if (defined('QUIQQER_SESSION_STARTED')) {
            $User = QUI::getUserBySession();
            $metadata['userId'] = $User->getUUID();
            $metadata['username'] = $User->getUsername();
        }

        if ($metadata !== []) {
            $extra['quiqqer'] = $metadata;
        }

        return $record->with(context: $context, extra: $extra);
    }
}
