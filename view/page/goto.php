<?php

# *******************************************************************
# CONTROLLER: REDIRECT SERVICE
# *******************************************************************

require_once(__DIR__ . '/../../include/class.Ad.php');

use PozarniPoplach\Ad;


$APPD = \Janmensik\Jmlib\AppData::getInstance();
$type = $APPD->getData('GOTO_TYPE') ?: '';
$id = (int)($APPD->getData('GOTO_ID') ?: 0);

if (empty($type) || empty($id)) {
    $APPD->setData('ERROR', 'Neplatný požadavek na přesměrování.');
    return;
}

switch ($type) {
    case 'ad':
        $Ad = new \PozarniPoplach\Ad($DB);
        // We use a manual where clause instead of getId() to avoid a deprecation
        // in the underlying Jmlib library's internal caching for single IDs.
        $res = $Ad->get(['ad.id = ' . (int)$id], null, 1);

        if (empty($res)) {
            $APPD->setData('ERROR', 'Reklama s tímto ID nebyla nalezena.');
            break;
        }

        $ad_data = $res[0];

        if (empty($ad_data['target_link'])) {
            $APPD->setData('ERROR', 'Tato reklama nemá nastavený cílový odkaz.');
            break;
        }

        // Log the hit
        $Ad->logLinkHit($id);

        // Perform redirect
        header('Location: ' . $ad_data['target_link']);
        exit;

    default:
        $APPD->setData('ERROR', 'Neznámý typ přesměrování.');
        break;
}
