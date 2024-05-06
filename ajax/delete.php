<?php

/**
 * This file contains package_quiqqer_log_ajax_delete
 */

use QUI\Exception;
use QUI\Utils\Security\Orthos;
use QUI\Utils\System\File;

/**
 * Delete a log
 *
 * @param string $file - Name of the log
 * @return void
 * @throws Exception
 */
function package_quiqqer_log_ajax_delete(string $file): void
{
    $log = VAR_DIR . 'log/' . $file;
    $log = Orthos::clearPath($log);

    File::unlink($log);
}

QUI::$Ajax->register(
    'package_quiqqer_log_ajax_delete',
    ['file'],
    'Permission::checkSU'
);
