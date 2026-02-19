<?php

/**
 * this file contains \QUI\Log\Manager
 */

namespace QUI\Log;

use DirectoryIterator;
use DusanKasan\Knapsack\Collection;
use QUI;
use QUI\Utils\System\File;

/**
 * Class Manager - log manager
 */
class Manager extends QUI\QDOM
{
    const LOG_DIR = VAR_DIR . 'log/';

    const LOG_ARCHIVE_DIR = self::LOG_DIR . 'archived/';

    /**
     * constructor
     *
     * @param array<string, mixed> $params
     */
    public function __construct(array $params = [])
    {
        // default
        $this->setAttributes([
            'sortOn' => 'mdate',
            'sortNy' => 'DESC'
        ]);

        $this->setAttributes($params);
    }

    /**
     * Deletes all log files which are older than the given amount of days
     *
     * @param int $days
     */
    public static function deleteLogsOlderThanDays(int $days): void
    {
        $OldLogs = Manager::getLogsOlderThanDays($days);

        foreach ($OldLogs as $OldLog) {
            unlink($OldLog->getRealPath());
        }
    }

    /**
     * Returns Log files created before the given amount of days
     * (Wrapper for the getLogsOlderThanSeconds()-function)
     *
     * @param int $days - Maximum for the logs in days
     * @return Collection|DirectoryIterator
     */
    public static function getLogsOlderThanDays(int $days): Collection | DirectoryIterator
    {
        return self::getLogsOlderThanSeconds($days * 24 * 60 * 60);
    }

    /**
     * Returns Log files created before the given amount of seconds
     *
     * @param int $seconds - Maximum age for the log in seconds
     * @return Collection|DirectoryIterator
     */
    public static function getLogsOlderThanSeconds(int $seconds): Collection | DirectoryIterator
    {
        $DirectoryIterator = new DirectoryIterator(self::LOG_DIR);
        $DirectoryCollection = Collection::from($DirectoryIterator);

        return $DirectoryCollection->filter(function ($log) use ($seconds) {
            /* @var $log DirectoryIterator */
            if ($log->isDot() || !$log->isFile() || $log->getExtension() != 'log') {
                return false;
            }

            $logAge = time() - $log->getMTime();

            return ($logAge >= $seconds);
        });
    }

    /**
     * Archives all log files which are older than the given amount of days
     *
     * @param int $days
     *
     * @throws QUI\Exception
     */
    public static function archiveLogsOlderThanDays(int $days): void
    {
        $OldLogs = Manager::getLogsOlderThanDays($days);

        $oldLogsGrouped = [];

        foreach ($OldLogs as $OldLog) {
            $date = date('Y-m-d', $OldLog->getCTime());
            $oldLogsGrouped[$date][] = $OldLog->getRealPath();
        }

        foreach ($oldLogsGrouped as $date => $oldLogFiles) {
            $zipPath = Manager::LOG_DIR . 'archived/' . $date . '.zip';

            QUI\Archiver\Zip::zipFiles($oldLogFiles, $zipPath);
        }
    }

    /**
     * Deletes all log files which are older than the given amount of days
     *
     * @param int $days
     */
    public static function deleteArchivedLogsOlderThanDays(int $days): void
    {
        $OldArchives = Manager::getArchivedLogsOlderThanDays($days);

        foreach ($OldArchives as $OldArchive) {
            unlink($OldArchive->getRealPath());
        }
    }

    /**
     * Returns archived log files created before the given amount of days
     * (Wrapper for the getArchivedLogsOlderThanSeconds()-function)
     *
     * @param int $days - Maximum age for the archived logs in days
     * @return Collection|DirectoryIterator
     */
    public static function getArchivedLogsOlderThanDays(int $days): Collection | DirectoryIterator
    {
        return self::getArchivedLogsOlderThanSeconds($days * 24 * 60 * 60);
    }

    /**
     * Returns archived log files created before the given amount of seconds
     *
     * @param int $seconds - Maximum age for the archived logs in seconds
     * @return Collection|DirectoryIterator
     */
    public static function getArchivedLogsOlderThanSeconds(int $seconds): Collection | DirectoryIterator
    {
        $DirectoryIterator = new DirectoryIterator(self::LOG_ARCHIVE_DIR);
        $DirectoryCollection = Collection::from($DirectoryIterator);

        return $DirectoryCollection->filter(function ($archive) use ($seconds) {
            /* @var $archive DirectoryIterator */
            if ($archive->isDot() || !$archive->isFile() || $archive->getExtension() != 'zip') {
                return false;
            }

            $archiveAge = time() - $archive->getMTime();

            return ($archiveAge >= $seconds);
        });
    }

    /**
     * Search logs
     * If search string is empty, all logs are returned
     *
     * @param string $search
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $search = ''): array
    {
        $dir = self::LOG_DIR;
        $list = [];
        $files = File::readDir($dir);

        $sortOn = $this->getAttribute('sortOn');
        $sortBy = $this->getAttribute('sortBy');

        if (empty($sortOn)) {
            $sortOn = 'mdate';
        }

        if (empty($sortBy)) {
            $sortBy = 'DESC';
        }

        if (empty($search)) {
            $search = false;
        }


        rsort($files);

        foreach ($files as $file) {
            if ($search && !str_contains($file, $search)) {
                continue;
            }

            // Ignore directories (e.g. archived/ folder)
            if (is_dir($dir . $file)) {
                continue;
            }

            $mtime = filemtime($dir . $file);

            if ($mtime === false) {
                continue;
            }

            $list[] = [
                'file' => $file,
                'mtime' => $mtime,
                'mdate' => date('Y-m-d H:i:s', $mtime)
            ];
        }

        // sort
        if ($sortOn == 'mdate') {
            usort($list, function ($a, $b) {
                return ($a['mtime'] < $b['mtime']) ? -1 : 1;
            });
        } else {
            if ($sortOn == 'file') {
                usort($list, function ($a, $b) {
                    return ($a['file'] < $b['file']) ? -1 : 1;
                });
            }
        }

        if ($sortBy == 'DESC') {
            rsort($list);
        }

        return $list;
    }
}
