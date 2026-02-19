<?php

/**
 * This file contains package_quiqqer_log_ajax_file
 */

/**
 * System logs
 *
 * @param string $file - Name of the log
 * @return array{isLogTrimmed: bool, data: string}|false
 */
function package_quiqqer_log_ajax_file(string $file): bool|array
{
    $User = QUI::getUserBySession();
    if (!$User->getPermission('quiqqer.packages.quiqqerlog.canUse') && !$User->isSU()) {
        return false;
    }

    // Return filename component of input so no files outside the log directory can be accessed
    $file = basename($file);

    $log = VAR_DIR . 'log/' . $file;
    $data = '';
    $isLogTrimmed = false;

    if (file_exists($log)) {
        // Log file bigger than 1MB?
        if (filesize($log) > 1000000) {
            // Get last thousand lines
            $data = getLastLinesOfFile($log, 1000, false);

            if (!is_string($data)) {
                $data = '';
            }

            $isLogTrimmed = true;
        } else {
            // Get the whole file
            $data = file_get_contents($log);

            if (!is_string($data)) {
                $data = '';
            }
        }
    }

    return [
        'isLogTrimmed' => $isLogTrimmed,
        'data' => $data
    ];
}


/**
 * Returns the last given number of lines of a file
 *
 * @param string $filepath - The file to get last lines from
 * @param int $lines - The amount of last lines to get
 * @param bool $adaptive - set to true when reading only a few lines
 *
 * @return bool|string
 * @author Torleif Berger, Lorenzo Stanco
 * @link http://stackoverflow.com/a/15025877/995958
 * @link https://gist.github.com/lorenzos/1711e81a9162320fde20
 * @license http://creativecommons.org/licenses/by/3.0/
 */
function getLastLinesOfFile(string $filepath, int $lines = 1, bool $adaptive = true): bool|string
{
    // Open file
    $f = @fopen($filepath, "rb");
    if ($f === false) {
        return false;
    }

    // Sets buffer size, according to the number of lines to retrieve.
    // This gives a performance boost when reading a few lines from the file.
    if (!$adaptive) {
        $buffer = 4096;
    } else {
        $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
    }

    // Jump to last character
    fseek($f, -1, SEEK_END);

    // Read it and adjust line number if necessary
    // (Otherwise the result would be wrong if file doesn't end with a blank line)
    if (fread($f, 1) != "\n") {
        $lines -= 1;
    }

    // Start reading
    $output = '';
    $chunk = '';

    // While we would like more
    while (ftell($f) > 0 && $lines >= 0) {
        // Figure out how far back we should jump
        $seek = min(ftell($f), $buffer);
        // Do the jump (backwards, relative to where we are)
        fseek($f, -$seek, SEEK_CUR);
        // Read a chunk and prepend it to our output
        $chunkData = fread($f, $seek);

        if (!is_string($chunkData)) {
            break;
        }

        $output = ($chunk = $chunkData) . $output;
        // Jump back to where we started reading
        fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
        // Decrease our line counter
        $lines -= substr_count($chunk, "\n");
    }

    // While we have too many lines
    // (Because of buffer size we might have read too many)
    while ($lines++ < 0) {
        // Find first newline and remove all text before that
        $output = substr($output, strpos($output, "\n") + 1);
    }

    // Close file and return
    fclose($f);

    return trim($output);
}

QUI::getAjax()->register(
    'package_quiqqer_log_ajax_file',
    ['file'],
    'Permission::checkSU'
);
