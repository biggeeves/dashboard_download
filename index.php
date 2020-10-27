<?php
/** @var RecordStatusDownload $module */

use DCC\RecordStatusDownload;

if (is_null($module) || !($module instanceof DCC\RecordStatusDownload\RecordStatusDownload)) {
    echo "Module Error";
    exit();
}
error_reporting(0);
$module->initialize();
if (!$module->hasPid()) {
    echo('Project ID is required');
} else {
    $module->controller();
    if ($module->returnJson) {
        REDCap::logEvent("Downloaded Record Status Dashboard");
        $module->transformData();
        header('Content-Type: application/json');
        echo json_encode($module->json);
        exit();
    } else {
        require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        $module->renderPage();
    }
}
