<?php

/**
 * This file contains package_quiqqer_log_ajax_canUserDownloadLogs
 */

use QUI\Interfaces\Users\User;
use QUI\Log\Permission;

/**
 * Returns if the given user can download log files.
 *
 * @param User|null $User $User
 *
 * @return boolean
 */
function package_quiqqer_log_ajax_canUserDownloadLogs(null|User $User = null): bool
{
    return Permission::canUserDownloadLogs($User);
}

QUI::getAjax()->register('package_quiqqer_log_ajax_canUserDownloadLogs');
