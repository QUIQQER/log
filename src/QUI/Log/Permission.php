<?php

/**
 * @author PCSG (Jan Wennrich)
 */

namespace QUI\Log;

use QUI;
use QUI\Interfaces\Users\User;

/**
 * Class Permission
 */
class Permission
{
    /**
     * Returns if the given user is allowed to download logs.
     * If no user is given the user is taken from the current session.
     *
     * @param User|null $User
     *
     * @return boolean
     */
    public static function canUserDownloadLogs(null | User $User = null): bool
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        if ($User->isSU()) {
            return true;
        }

        if (!$User->getPermission('quiqqer.packages.quiqqerlog.canUse')) {
            return false;
        }

        return $User->canUseBackend();
    }
}
