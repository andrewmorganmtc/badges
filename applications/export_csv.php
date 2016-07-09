<?php

$path = '../../../../';
include $path . 'wp-blog-header.php';

header("Content-type: text/x-csv");
header("Content-Disposition: attachment; filename='badge_applications_" . $_REQUEST['badge_id'] . ".csv'");

$fp = tmpfile();

$column_values = [
    'Applicant Name',
    'Applicant Email Address',
    'Badge applied for',
    'Evidence',
];

fputcsv($fp, $column_values);

if (!empty($_REQUEST['badge_id'])) {

    $query = "SELECT * FROM `" . $wpdb->prefix . "badge_applications`
                WHERE `badge_id` = " . $_REQUEST['badge_id'];

    $badge_applications = $wpdb->get_results($query);

    if (!empty($badge_applications)) {
        foreach ($badge_applications as $badge_application) {

            $application_details = Badges::getApplication($badge_application->uid, true);
            $badge_details = Badges::getBadge($application_details['badge_id']);

            $application_values = [
                $application_details['user']->data->display_name,
                $application_details['user']->data->user_email,
                $badge_details['post_title'],
                Badges::getBadgeUrl($application_details['application_id'], $application_details['badge_id'])
            ];


            fputcsv($fp, $application_values);

        }
    }
}

fseek($fp, 0);
fpassthru($fp);
fclose($fp);

exit();
