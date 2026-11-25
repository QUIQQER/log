<?php

/**
 * This file contains \QUI\Log\Admin class
 */

namespace QUI\Log;

use QUI;

/**
 * QUIQQER logging service
 */
class Admin
{
    /**
     * event : on admin load
     */
    public static function onAdminLoad(): void
    {
        $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/log');

        if ($Package->getConfig()?->get('browser_logs', 'debug')) {
            echo '<script type="text/javascript">
                  /* <![CDATA[ */
                    if ( typeof monitorEvents !== \'undefined\' )
                    {
                        monitorEvents( document.body, \'click\' );
                        monitorEvents( document.body, \'mousedown\' );
                        monitorEvents( document.body, \'dblclick\' );
                    }
                  /* ]]> */
                  </script>
            ';
        }
    }

    /**
     * event : on admin load footer
     */
    public static function onAdminLoadFooter(): void
    {
        $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/log');

        if ($Package->getConfig()?->get('log', 'logAdminJsErrors')) {
            echo JavaScriptErrorLogger::getScript();
        }
    }
}
