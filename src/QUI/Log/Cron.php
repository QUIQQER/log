<?php

/**
 * This filoe contains \QUI\Log\Cron
 */

namespace QUI\Log;

use DateInterval;
use DateTime;
use QUI;
use PHPMailer\PHPMailer\Exception as PhPHPMailerException;

use function defined;
use function file_exists;
use function gethostname;
use function phpversion;
use function number_format;

/**
 * Class Cron / Log Cron
 */
class Cron
{
    /**
     * Send the logs from the last day
     *
     * @param array<string, mixed> $params
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
        $attachedFiles = [];
        $skippedFiles = [];

        $host = defined('HOST') ? (string)HOST : '';
        $serverHost = (string)gethostname();

        if ($host === '') {
            $host = $serverHost;
        }
        $quiqqerVersion = QUI::version();
        $phpVersion = phpversion();
        $subject = 'Logs from the last day';

        if (!empty($host)) {
            $subject = '[' . $host . '] ' . $subject;
        }

        $Mailer->addRecipient($params['email']);
        $Mailer->setSubject($subject);

        foreach ($result as $entry) {
            if (!isset($entry['file'])) {
                continue;
            }

            $file = $logDir . $entry['file'];

            if (file_exists($file)) {
                $size = filesize($file);

                if ($size && $size <= 5242880) { // 5MB max
                    $Mailer->addAttachments($file);
                    $attachedFiles[] = [
                        'file' => $file,
                        'size' => $size
                    ];
                } else {
                    $skippedFiles[] = [
                        'file' => $file,
                        'size' => $size
                    ];
                }
            }
        }

        $body .= '<h2>Daily Log Report</h2>';
        $body .= '<p>System information:</p>';
        $body .= '<ul>';
        $body .= '<li><strong>System:</strong> ' . $host . '</li>';
        $body .= '<li><strong>Server:</strong> ' . $serverHost . '</li>';
        $body .= '<li><strong>QUIQQER Version:</strong> ' . $quiqqerVersion . '</li>';
        $body .= '<li><strong>PHP Version:</strong> ' . $phpVersion . '</li>';
        $body .= '<li><strong>Date:</strong> ' . $Date->format('Y-m-d') . '</li>';
        $body .= '<li><strong>Log directory:</strong> ' . $logDir . '</li>';
        $body .= '</ul>';

        $body .= '<p><strong>Attachments:</strong> ' . count($attachedFiles) . '</p>';

        if (!empty($attachedFiles)) {
            $body .= '<ul>';

            foreach ($attachedFiles as $attachedFile) {
                $body .= '<li>' . $attachedFile['file']
                    . ' (' . number_format((float)$attachedFile['size'] / 1024, 2) . ' KB)</li>';
            }

            $body .= '</ul>';
        }

        if (!empty($skippedFiles)) {
            $body .= '<p><strong>Skipped files (too large for attachment):</strong></p>';
            $body .= '<ul>';

            foreach ($skippedFiles as $skippedFile) {
                $body .= '<li>' . $skippedFile['file']
                    . ' (' . number_format((float)$skippedFile['size'] / 1024, 2) . ' KB)</li>';
            }

            $body .= '</ul>';
        }

        $Mailer->setBody($body);
        $Mailer->send();
    }

    /**
     * Archive old log files
     *
     * @param array<string, mixed> $params
     * @param QUI\Cron\Manager $CronManager
     *
     * @throws QUI\Exception
     */
    public static function archiveLogs(array $params, QUI\Cron\Manager $CronManager): void
    {
        $Package = QUI::getPackage('quiqqer/log');
        $Config = $Package->getConfig();

        $minLogAgeForArchiving = (int)$Config?->getValue('log_cleanup', 'minLogAgeForArchiving');
        $isLogArchivingEnabled = (int)$Config?->getValue('log_cleanup', 'isArchivingEnabled');

        if ($isLogArchivingEnabled) {
            Manager::archiveLogsOlderThanDays($minLogAgeForArchiving);

            // Files are copied into the zip file, so now delete them
            Manager::deleteLogsOlderThanDays($minLogAgeForArchiving);
        }
    }

    /**
     * Deletes old log files (and archives)
     *
     * @param array<string, mixed> $params
     * @param QUI\Cron\Manager $CronManager
     *
     * @throws QUI\Exception
     */
    public static function cleanupLogsAndArchives(array $params, QUI\Cron\Manager $CronManager): void
    {
        $Package = QUI::getPackage('quiqqer/log');
        $Config = $Package->getConfig();

        $minLogAgeForDelete = (int)$Config?->getValue('log_cleanup', 'minLogAgeForDelete');
        $minArchiveAgeForDelete = (int)$Config?->getValue('log_cleanup', 'minArchiveAgeForDelete');
        $isArchiveDeletionEnabled = (int)$Config?->getValue('log_cleanup', 'isArchiveDeletionEnabled');

        Manager::deleteLogsOlderThanDays($minLogAgeForDelete);

        if ($isArchiveDeletionEnabled) {
            Manager::deleteArchivedLogsOlderThanDays($minArchiveAgeForDelete);
        }
    }
}
