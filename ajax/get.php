<?php

/**
 * This file contains package_quiqqer_log_ajax_get
 */

/**
 * Return the log list
 *
 * @param integer|string $page
 * @param integer|string $limit
 * @param string $search
 * @param string $sortOn
 * @param string $sortBy
 *
 * @return array
 */
function package_quiqqer_log_ajax_get(
    int|string $page,
    int|string $limit,
    string $search = '',
    string $sortOn = 'mdate',
    string $sortBy = 'DESC'
): array {
    $LogManager = new QUI\Log\Manager();

    if (empty($sortOn)) {
        $sortOn = 'mdate';
    }

    if (empty($sortBy)) {
        $sortBy = 'DESC';
    }

    $LogManager->setAttribute('sortOn', $sortOn);
    $LogManager->setAttribute('sortBy', $sortBy);

    $list = $LogManager->search($search);

    return QUI\Utils\Grid::getResult($list, $page, $limit);
}

QUI::$Ajax->register(
    'package_quiqqer_log_ajax_get',
    ['page', 'limit', 'search', 'sortOn', 'sortBy'],
    'Permission::checkSU'
);
