<?php

/**
 * This file contains package_quiqqer_log_ajax_canUserDownloadLogs
 */

use QUI\Interfaces\Users\User;

/**
 * Returns if the given user can download log files.
 *
 * @param User $User
 *
 * @return boolean
 */
function package_quiqqer_log_ajax_canUserDownloadLogs(User $User = null): bool
{
    return \QUI\Log\Permission::canUserDownloadLogs($User);
}

QUI::$Ajax->register('package_quiqqer_log_ajax_canUserDownloadLogs');
