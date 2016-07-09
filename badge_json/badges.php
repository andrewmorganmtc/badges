<?php
$path = '../../../../';
require_once $path . 'wp-blog-header.php';
header('Content-Type: application/json');
http_response_code(200);

/*
 * Return badge assertion JSON based on application ID
 */
if ($_REQUEST['application_request'] == true && !empty($_REQUEST['application_id']) && is_numeric($_REQUEST['application_id'])) {

    $application_details = Badges::getApplication($_REQUEST['application_id'], false, 'application_id');
    $badge_details = Badges::getBadge($application_details['badge_id']);

    if (empty($application_details)) {
        exit('Invalid badge');
    }

    $badge_assertion_address = get_bloginfo('wpurl') . '/badge_assertion_' . $application_details['application_id'] . '.json';
    $badge_address = get_bloginfo('wpurl') . '/badge_' . $application_details['application_id'] . '.json';

    $params = [
        "uid" => (string)$application_details['application_id'],
        "recipient" => [
            "identity" => (string)Badges::generateHash($application_details['badge_applicant_email_address'], $application_details['salt']),
            "salt" => (string)$application_details['salt'],
            "hashed" => "true",
            "type" => "email",
        ],
        "issuedOn" => (string)strtotime($application_details['created_on']),
        "badge" => (string)$badge_address,
        "evidence" => get_permalink($badge_details['ID'])  . '?application_id=' . $_REQUEST['application_id'],
        "verify" => [
            "type" => "hosted",
            "url" => (string)$badge_assertion_address
        ]
    ];

    exit(json_encode($params));

}

/*
 * Return badge JSON based on application ID
 */
if ($_REQUEST['badge_request'] == true && !empty($_REQUEST['application_id']) && is_numeric($_REQUEST['application_id'])) {

    $application_details = Badges::getApplication($_REQUEST['application_id'], false, 'application_id');
    $badge_details = Badges::getBadge($application_details['badge_id']);

    $params = [
        "name" => (string)$badge_details['post_title'],
        "description" => (string)$badge_details['badge_summary'],
        "image" => $badge_details['url'],
        "criteria" => get_permalink($badge_details['ID'])  . '?application_id=' . $_REQUEST['application_id'],
        "issuer" => (string)get_bloginfo('wpurl') . "/badge_issuer_" . $badge_details['badge_issuer'] . ".json",
    ];

    exit(json_encode($params));
}

/**
 * Return badge issuer JSON
 */
if ($_REQUEST['badge_issuer_request'] == true && !empty($_REQUEST['issuer_id']) && is_numeric($_REQUEST['issuer_id'])) {

    $issuer_details = Badges::getIssuer($_REQUEST['issuer_id']);

    $params = [
        "name" => (string)$issuer_details['name'],
        "url" => (string)$issuer_details['url'],
    ];

    exit(json_encode($params));
}
