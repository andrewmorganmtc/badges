<?php

$path = '../../../../';
require_once $path . 'wp-blog-header.php';

if (!empty($_REQUEST['application_id']) && is_numeric($_REQUEST['application_id'])) {

    $application_id = $_REQUEST['application_id'];

    Badges::makePDF($application_id);

}
