<?php

/**
 * This filoe contains \QUI\Log\Cron
 */

namespace QUI\Log;

use DateInterval;
use DateTime;
use QUI;
use \PHPMailer\PHPMailer\Exception as PhPHPMailerException;

use function file_exists;
use function number_format;

/**
 * Class Cron / Log Crons
 *
 * @package quiqqer/log
 * @author  Henning Leutz (PCSG)
 * @author  Jan Wennrich (PCSG)
 */
class Cron
{
    /**
     * Send the logs from the last day
     *
     * @param array $params
     * @param \QUI\Cron\Manager $CronManager
     *
     * @throws QUI\Exception|PhPHPMailerException
     */
    public static function sendLogsFromLastDay(array $params, QUI\Cron\Manager $CronManager): void
    {
        if (!isset($params['email'])) {
            throw new QUI\Exception('Need a email parameter to send the log');
        }

        $logDir = VAR_DIR . 'log/';

        $Date = new DateTime();
        $Date->add(DateInterval::createFromDateString('yesterday'));

        $Mailer = new QUI\Mail\Mailer();
        $LogManager = new QUI\Log\Manager();

        $body = '';
        $result = $LogManager->search($Date->format('Y-m-d') . '.log');

        $Mailer->addRecipient($params['email']);
        $Mailer->setSubject('Logs from the last day');

        $maxMegaByte = 5;

        foreach ($result as $entry) {
            if (!isset($entry['file'])) {
                continue;
            }

            $file = $logDir . $entry['file'];

            if (file_exists($file)) {
                $size = QUI\Utils\System\File::getFileSize($file);
                $size = number_format($size / 1048576);

                if ($size <= $maxMegaByte) {
                    $Mailer->addAttachments($file);
                } else {
                    $body .= '<br />File ' . $file . ' is too big for an attachment';
                }
            }
        }

        $Mailer->setBody($body);
        $Mailer->send();
    }

    /**
     * Archive old log files
     *
     * @param $params
     * @param $CronManager
     *
     * @throws QUI\Exception
     */
    public static function archiveLogs($params, $CronManager): void
    {
        $Package = QUI::getPackage('quiqqer/log');
        $Config = $Package->getConfig();

        $minLogAgeForArchiving = $Config->getValue('log_cleanup', 'minLogAgeForArchiving');
        $isLogArchivingEnabled = $Config->getValue('log_cleanup', 'isArchivingEnabled');

        if ($isLogArchivingEnabled) {
            Manager::archiveLogsOlderThanDays($minLogAgeForArchiving);

            // Files are copied into the zip file, so now delete them
            Manager::deleteLogsOlderThanDays($minLogAgeForArchiving);
        }
    }

    /**
     * Deletes old log files (and archives)
     *
     * @param $params
     * @param $CronManager
     *
     * @throws QUI\Exception
     */
    public static function cleanupLogsAndArchives($params, $CronManager): void
    {
        $Package = QUI::getPackage('quiqqer/log');
        $Config = $Package->getConfig();

        $minLogAgeForDelete = $Config->getValue('log_cleanup', 'minLogAgeForDelete');
        $minArchiveAgeForDelete = $Config->getValue('log_cleanup', 'minArchiveAgeForDelete');
        $isArchiveDeletionEnabled = $Config->getValue('log_cleanup', 'isArchiveDeletionEnabled');

        Manager::deleteLogsOlderThanDays($minLogAgeForDelete);

        if ($isArchiveDeletionEnabled) {
            Manager::deleteArchivedLogsOlderThanDays($minArchiveAgeForDelete);
        }
    }
}
