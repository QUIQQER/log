<?php

/**
 * Returns if the given user can download log files.
 *
 * @param \QUI\Interfaces\Users\User $User
 *
 * @return boolean
 */
function package_quiqqer_log_ajax_canUserDownloadLogs($User = null)
{
    return \QUI\Log\Permission::canUserDownloadLogs($User);
}

QUI::$Ajax->register('package_quiqqer_log_ajax_canUserDownloadLogs');
