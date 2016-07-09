<?php
/**
 * Badges
 *
 * Manage the reviewal process and allocation of badges
 *
 * @author Andrew Morgan <andrew.morgan@mtcmedia.co.uk>
 */

use mikehaertl\wkhtmlto\Pdf;

class Badges {
    /**
     * userHasBadge()
     *
     * @param $user_id ID of user to check
     * @param $badge_id ID of badge to check user against
     *
     * @return bool Return result of true if user/badge combination found
     */
    public static function userHasBadge($user_id, $badge_id)
    {

        global $wpdb;

        $query = "SELECT `uid` FROM `" . $wpdb->prefix . "badge_applications`
                WHERE `badge_id` = '" . $badge_id ."'
                AND `account_id` = '" . $user_id . "'";

        $wpdb->get_results($query);

        if ($wpdb->num_rows > 0) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Badges::userEmailExists()
     *
     * Check if an email address already exists
     *
     * @return bool true means it exists
     */
    public static function userEmailExists($email_address)
    {
        global $wpdb;

        $query = "SELECT `id` FROM `" . $wpdb->prefix . "users`
                WHERE `user_email` = '" . $email_address . "'";

        $wpdb->get_results($query);

        if ($wpdb->num_rows > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Badges::changeEmailAddress()
     *
     * Update badge email address
     */
    public static function changeEmailAddress($old_email_address, $new_email_address)
    {
        global $wpdb;

        $query = "SELECT `badge_applicant_email_address` FROM `" . $wpdb->prefix . "badge_applications`
                    WHERE `badge_applicant_email_address` = '" . $old_email_address . "'
                    GROUP BY `badge_applicant_email_address`";

        $results = $wpdb->get_results($query, ARRAY_A);

        // Update user table with new email address
        $wpdb->update($wpdb->prefix . 'users', [
            'user_email' => $new_email_address,
            'user_login' => $new_email_address
        ], [
               'user_email' => $old_email_address, // Where ID equals
            ]
        );

        // Update badge application table with new email address
        $wpdb->update($wpdb->prefix . 'badge_applications', [
            'badge_applicant_email_address' => $new_email_address
            ], [
               'badge_applicant_email_address' => $old_email_address, // Where ID equals
            ]
        );

        foreach ($results as $updated_application) {
            self::updateUserEmail($updated_application['badge_applicant_email_address']);
        }

    }

    /**
     * Badges::getStaticBadge()
     *
     * Return all of the badge details
     *
     * @param $badge_id ID of badge
     *
     * @return
     */
    public static function getStaticBadge($badge_id)
    {

        $post_meta = get_post_meta($badge_id);

        $badge_image = wp_get_attachment_metadata($post_meta['badge_image'][0]);

        $badge_image['url'] = get_site_url() . '/wp-content/uploads/' . $badge_image['file'];

        $badge_image['path'] = '/wp-content/uploads/' . $badge_image['file'];

        return $badge_image;

    }

    /**
     * Badges::getBadge()
     *
     * Return all of the badge details
     *
     * @param $badge_id ID of badge
     *
     * @return
     */
    public static function getBadge($badge_id, $comments = false)
    {

        $post = get_post($badge_id);

        $post_meta = get_post_meta($badge_id);

        $badge_image = self::getStaticBadge($badge_id);

        $badge_details = [
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_date' => $post->post_date,
            'url' => $badge_image['url'],
            'badge_image_path' => $badge_image['path'],
            'badge_image_id' => $post_meta['badge_image'][0],
            'badge_summary' => $post_meta['badge_summary'][0],
            'badge_introduction' => $post_meta['badge_introduction'][0],
            'badge_criteria' => $post_meta['badge_criteria'][0],
            'badge_prerequisites' => $post_meta['badge_prerequisites'][0],
            'badge_evidence' => $post_meta['badge_evidence'][0],
            'badge_issuer' => $post_meta['badge_issuer'][0],
        ];

        return $badge_details;

    }

    /**
     * Badges::getApplication()
     *
     * Return Badge Application details
     *
     * @param $application_id ID of badge application to check
     * @param $return_user bool lookup user details
     * @param $find_column Switch between ID of database and application_id used for obfuscation
     *
     * @return Array $badge_application
     */
    public static function getApplication($application_id, $return_user = false, $find_column = 'uid')
    {

        global $wpdb;

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_applications`
                    WHERE " . $find_column . " = '" . $application_id . "'";

        $badge_application = $wpdb->get_row($query, ARRAY_A);

        $badge_application['evidence'] = self::getBadgeEvidence($badge_application['uid']);

        // $badge_application['comments'] = self::getApplicationComments($badge_application['uid']);

        if ($return_user === true) {
            $badge_application['user'] = get_userdata($badge_application['account_id']);
            $badge_application['user_meta'] = get_user_meta($badge_application['user']->data->ID);
        }

        return $badge_application;

    }

    /**
     * Badges::getApplicationComments()
     *
     * Return Badge Application details
     *
     * @param $application_id unique ID of badge application to check
     *
     * @return Array $badge_application
     */
    public static function getApplicationComments($application_uid)
    {

        global $wpdb;

        $application_comments = []; // return empty array even if no results

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_comments`
                    WHERE `application_id` = '" . $application_uid . "'
                    ORDER BY `date` ASC";

        $application_comments = $wpdb->get_results($query, ARRAY_A);

        return $application_comments;

    }

    /**
     * Badges::getApplicationByUser()
     *
     * Return Badge Application details
     *
     * @param $application_id ID of badge application to check
     * @param $return_user bool lookup user details
     * @param $find_column Switch between ID of database and application_id used for obfuscation
     *
     * @return Array $badge_application
     */
    public static function getApplicationByUser($user_id, $badge_id)
    {

        global $wpdb;

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_applications`
                    WHERE `account_id` = '" . $user_id . "'
                    AND `badge_id` = '" . $badge_id . "'";

        $badge_application = $wpdb->get_row($query, ARRAY_A);

        return $badge_application;

    }

    /**
     * Badges::createApplication()
     *
     * Create a new application
     *
     * @param $user_id ID of applying user
     * @param $badge_id Badge ID to apply for
     *
     * @return Mixed Int Unique DB ID of newly created application | Error
     */
    public static function createApplication($user_id, $badge_id, $approve = false)
    {
        global $wpdb;
        global $errors;

        $badge_details = self::getBadge($badge_id);

        $user_details = get_userdata($user_id);

        if (!self::userHasBadge($user_id, $badge_id)) {

            if ($approve === true) {
                $application_status = 1;
            } else {
                $application_status = 0;
            }

            $application_id = self::generateUniqueID();

            $badge_image = self::getStaticBadge($badge_id);

            if (is_file(rtrim(ABSPATH, ',') . $badge_image['path'])) {

                $wpdb->insert(
                    $wpdb->prefix . 'badge_applications', [
                        'application_id' => $application_id,
                        'badge_id' => $badge_details['ID'],
                        'baked_badge_image' => $badge_image['path'],
                        'account_id' => $user_details->data->ID,
                        'badge_applicant_email_address' => $user_details->data->user_email,
                        'created_on' => date("Y-m-d H:i:s"),
                        'salt' => self::generateUniqueID(),
                        'application_status' => $application_status,
                        'approved_on' => ($approve ? date("Y-m-d H:i:s") : ''),
                    ]
                );

                return $wpdb->insert_id;

            } else {
                $errors['badge_not_valid'] = $badge_details['post_title'] . ' does not have a valid image and cannot be used in an application';
            }

        } else {
            $errors['already_applied'] = 'User has already applied for this badge';
        }
    }

    /**
     * Badges::getIssuer()
     *
     * Get issuer details
     *
     * @param int $issuer_id
     * @return Array
     */
    public static function getIssuer($issuer_id)
    {
        global $wpdb;

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_issuers`
                    WHERE `id` = '" . $issuer_id ."'";

        $result = $wpdb->get_row($query, ARRAY_A);

        return $result;

    }

    /**
     * Badges::get()
     *
     * Get all badges
     *
     * @return Array
     */
    public static function get()
    {

        global $wpdb;

        $query = "SELECT * FROM `" . $wpdb->prefix . "posts`
                    WHERE `post_type` = 'badges'
                    AND `post_status` = 'publish'";

        $badges = $wpdb->get_results($query);

        return $badges;

    }

    /**
     * Badges::createZip()
     *
     * Given a set of badge files... package them up as zip archives
     *
     * @return Array
     */
    public static function createZip($files = [], $destination = null, $remove_folder_path = '', $overwrite = false)
    {
        //if the zip file already exists and overwrite is false, return false
        if (file_exists($destination) && !$overwrite) {
            return false;
        }

        // vars
        $valid_files = [];

        // if files were passed in...
        if (!empty($files)) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $valid_files[] = $file;
                }
            }
        }

        // if we have good files...
        if (count($valid_files)) {

            // create the archive
            $zip = new ZipArchive();

            if ($zip->open($destination, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
                return false;
            }

            // add the files
            foreach($valid_files as $file) {
                // add full path file but then strip path for naming it.
                $filename = basename($file);
                $zip->addFile($file, $filename);
            }

            // close the zip -- done!
            $zip->close();

            // check to make sure the file exists
            return file_exists($destination);

        } else {
            return false;
        }
    }

    /**
     * Badges::getBadgeFilenames()
     *
     * Given a set of application IDs... return list of files
     *
     * @return Array of filenames
     */
    public static function getBadgeFilenames($application_ids = [])
    {
        // return array even if no results
        $badge_images = [];

        foreach ($application_ids as $application_id) {

            $application_details = self::getApplication($application_id, true, 'uid');

            // Full path to home
            $badge_images[] = rtrim(ABSPATH, '/') . self::getBakedBadge($application_details['application_id']);

        }

        return $badge_images;

    }

    /**
     * Badges::getBadgeUrl()
     *
     * Make up badge url
     *
     * @return string URL
     */
    public static function getBadgeUrl($application_id, $badge_id)
    {

        $badge_url = get_permalink($badge_id) . '/?application_id=' . $application_id;

        return $badge_url;

    }

    /**
     * Badges::generateHash();
     *
     * Uses Email address and pregenerated salt to generate a sha256 hash.
     *
     * @param string $email alphanumeric email address
     * @param string $salt alphanumeric salt
     *
     */
    public static function generateHash($email, $salt)
    {
        return "sha256$" . hash("sha256", ($email . $salt));
    }

    /**
     * Badges::bakeBadge()
     *
     * Create a badge with metadata attached
     *
     * @return string $baked_badge
     */
    public static function bakeBadge($application_id)
    {
        global $wpdb;

        // Get application details
        $application_details = self::getApplication($application_id, true, 'application_id');

        // Get original badge address
        $post_meta = get_post_meta($application_details['badge_id']);

        // Replace site URL with site path to get the badge attachment from the post
        $original_badge = rtrim(ABSPATH, '/') . str_replace(get_site_url(), '', wp_get_attachment_image_src($post_meta['badge_image'][0])[0]);

        // check the original badge image is present... skip over if not
        if (is_file($original_badge)) {

            // Work out the address for main badge assertion JSON
            $badge_json_address = get_site_url() . "/badge_assertion_" . $application_details['application_id'] . ".json";

            // Unique filename for newly baked badge
            $baked_badge = self::getUserUploadDirectory($application_details['account_id']) . time() . rand() . '_baked.png';

            // Get the filename without path for adding to database
            $baked_badge_filename = str_replace(rtrim(ABSPATH, '/'), '', $baked_badge);

            // Bake the image and run the command
            $bake_me_address = "https://backpack.openbadges.org/baker?assertion=" . $badge_json_address;
            $new_badge_image = file_get_contents($bake_me_address);

            if (!empty($new_badge_image)) {

                $fp = fopen($baked_badge, "w");
                fwrite($fp, $new_badge_image);
                fclose($fp);

                $wpdb->update($wpdb->prefix . 'badge_applications', [
                        'baked_badge_image' => $baked_badge_filename
                    ], [
                       'uid' => $application_details['uid'], // Where ID equals
                    ]
                );

            }

        }

        // Return new image filename
        return $baked_badge_filename;

    }

    /**
     * Badges::getBakedBadge()
     *
     * Check for baked badge associated with this application
     *
     * @return string $badge_filename
     */
    public static function getBakedBadge($application_id)
    {

        // Grab all application details
        $application_details = self::getApplication($application_id, true, 'application_id');

        if (!empty($application_details['baked_badge_image'])) {
            $image_to_check = rtrim(ABSPATH, '/') . $application_details['baked_badge_image'];
        } else {
            $image_to_check = null;
        }

        $filename_without_path = str_replace(ABSPATH . 'wp-content/uploads/', '', $image_to_check);
        $year_month_pattern = "/[0-9]{4}\/[0-9]{2}/";

        if (preg_match($year_month_pattern, $filename_without_path, $matches)) {
            // If file is in format "2009/02" it is not a baked badge
            $badge_filename = self::bakeBadge($application_details['application_id']);
        } elseif (file_exists($image_to_check)) { // regenerate if the stored image does not exist
            $badge_filename = $application_details['baked_badge_image'];
        } else { // Bake a new badge image
            $badge_filename = self::bakeBadge($application_details['application_id']);
        }

        return $badge_filename;
    }

    /**
     * Badges::getUser()
     *
     * Return user details + meta of a specific user ID
     * alternatively dropping back to the current user
     *
     * @param $user_id optionally include a specific USER ID
     *
     * @return Array user details
     */
    public static function getUser($user_id)
    {

        if (empty($user_id)) {
            $user_id = $user_id;
        } else {
            global $current_user;
            $user_id = $current_user->data->ID;
        }

        $user_details = []; // initialise empty array

        // Get WP User object
        $user_data = get_userdata($user_id);

        // Get any meta data associated with the WP User object ID
        $user_meta_data = get_user_meta($user_id);
        $user_details = [
            'user' => $user_data,
            'user_meta' => $user_meta_data,
        ];

        return $user_details;
    }

    /**
     * Badges::getUsers()
     *
     * Get all users of a certain type
     *
     * @return Array of user ID's
     */
    public static function getUsers()
    {
        global $wpdb;

        $query = "SELECT `id` FROM `" . $wpdb->prefix . "users`
                    LEFT JOIN `" . $wpdb->prefix . "usermeta`
                    ON `" . $wpdb->prefix . "users`.`id` = `" . $wpdb->prefix . "usermeta`.`user_id`
                    GROUP BY `user_email`";

        $results = $wpdb->get_results($query, ARRAY_A);

        return $results;
    }

    /**
     * Badges::getUserUploadDirectory()
     *
     * Return directory name based on user ID
     *
     * @return string $user_dirname path to file
     */
    public static function getUserUploadDirectory($user_id)
    {

        if (empty($user_id)) {
            global $current_user;
            $user = $current_user;
        } else {
            $user = get_user_by('id', $user_id);
        }

        $upload_dir = wp_upload_dir();
        $user_dirname = $upload_dir['basedir'] . '/' . $user->data->user_nicename . '/';

        if (!is_dir($user_dirname)) {
            mkdir($user_dirname);
        }

        return $user_dirname;

    }

    /**
     * Badges::applicationStatusEmail()
     *
     * Trigger application status email
     *
     * @param int $application_id UID of badge application
     * @param int $application_status Approved, revoked etc.
     *
     * @return bool
     */
    public static function applicationStatusEmail($application_id, $application_status, $text_to_include = '')
    {
        $admin_email_address = get_bloginfo('admin_email');

        $email_header  = "From: " . $admin_email_address . ",\r\n";
        $email_header .= "Reply-To:" . $admin_email_address . "\r\n";
        $email_header .= "Return-Path: " . $admin_email_address . "\r\n";
        $email_header .= "MIME-Version: 1.0" . "\r\n";
        $email_header .= "Content-type:text/html;charset=utf-8" . "\r\n";

        $application_details = Badges::getApplication($application_id, true);
        $badge_details = self::getBadge($application_details['badge_id']);

        switch ($application_status) {
            case '0': // pending
                $email_subject = 'Your badge has marked as pending';
                break;
            case '1': // approved
                $email_subject = 'You have been awarded a badge';
                break;
            case '2': // revoked
                $email_subject = 'Your badge has been revoked';
                break;
            case '3': // declined
                $email_subject = 'Your badge application has been declined';
                break;
        }

        ob_start();
        include get_template_directory() . '/emails/status_email.php';
        $email_body = ob_get_clean();

        mail($application_details['badge_applicant_email_address'], $email_subject, $email_body, $email_header);

    }

    /**
     * Badges::randomApplicationID()
     *
     * Get a random application ID
     *
     * @return int $application_id
     */
    public static function randomApplicationID($application_status)
    {
        global $wpdb;

        $query = "SELECT `application_id` FROM `" . $wpdb->prefix . "badge_applications`
                WHERE `application_status` = '" . $application_status ."'
                ORDER BY RAND()
                LIMIT 1";

        $result = $wpdb->get_results($query, ARRAY_A);

        return $result[0]['application_id'];

    }

    /**
     * Badges::filesArrayCleanup()
     *
     * Reorder the files array to a more logical order
     *
     * @return Array
     */
    public static function filesArrayCleanup(&$file_post)
    {

        $file_array = [];
        $file_count = count($file_post['name']);
        $file_keys = array_keys($file_post);

        for ($i=0; $i < $file_count; $i++) {
            foreach ($file_keys as $key) {
                $file_array[$i][$key] = $file_post[$key][$i];
            }
        }

        return $file_array;

    }

    /**
     * Badges::addUserViaEmail()
     *
     * Add a new user with only the email address available
     *
     * @param string $email_address
     *
     * @return Int New user ID
     */
    public static function addUserViaEmail($email_address, $notify = true)
    {
        global $errors;

        $user_details = [
            'user_login' => $email_address,
            'user_email' => $email_address,
            'user_registered' => date("Y-m-d H:i:s"),
            'user_pass' => substr(md5(rand(0, 1000000)), 0, 10), // generates 10 digit alphanumeric string
        ];

        $new_user_id = wp_insert_user($user_details);

        // return false on any error
        if (is_wp_error($user)) {
            foreach($user->errors as $error) {
                $errors[] = $error[0];
            }
            return false;
        }

        if (!empty($notify) && $notify === true) {
            self::sendNewUserEmail($new_user_id);
        }

        return $new_user_id;

    }

    /**
     * Badges::sendNewUserEmail()
     *
     * Trigger New User Email to user created associated
     * with a new badge application.
     *
     * @param $user_id ID of user to send email to
     *
     * @return void
     */
    public static function sendNewUserEmail($user_id)
    {

        $user = get_userdata($user_id);

        $new_user_email = $user->data->user_email;

        $admin_email_address = get_bloginfo('admin_email');

        $email_header  = "From: " . $admin_email_address . ",\r\n";
        $email_header .= "Reply-To:" . $admin_email_address . "\r\n";
        $email_header .= "Return-Path: " . $admin_email_address . "\r\n";
        $email_header .= "MIME-Version: 1.0" . "\r\n";
        $email_header .= "Content-type:text/html;charset=utf-8" . "\r\n";

        $email_subject = 'You have been awarded a badge.';
        ob_start();
        include get_template_directory() . '/emails/new_user_email.php';
        $email_body = ob_get_clean();

        mail($new_user_email, $email_subject, $email_body, $email_header);

    }
    /**
     * Badges::updateUserEmail()
     *
     *
     *
     * @param $user_id ID of user to send email to
     *
     * @return void
     */
    public static function updateUserEmail($updated_user_email)
    {

        $admin_email_address = get_bloginfo('admin_email');

        $email_header  = "From: " . $admin_email_address . ",\r\n";
        $email_header .= "Reply-To:" . $admin_email_address . "\r\n";
        $email_header .= "Return-Path: " . $admin_email_address . "\r\n";
        $email_header .= "MIME-Version: 1.0" . "\r\n";
        $email_header .= "Content-type:text/html;charset=utf-8" . "\r\n";

        $email_subject = 'Your email address has been updated';
        ob_start();
        include get_template_directory() . '/emails/email_address_change.php';
        $email_body = ob_get_clean();

        mail($updated_user_email, $email_subject, $email_body, $email_header);

    }

    /**
     * Badges::generateUniqueID()
     *
     * Generate a unique number
     *
     * @return Int unique number
     */
    public static function generateUniqueID() {
        return substr(number_format(hexdec(uniqid(mt_rand(), true)), 0, '', ''), 0, 17);
    }

    /**
     * Badges::makePDF()
     *
     * Make a PDF and generate a unique filename
     *
     * @return Int unique number
     */
    public static function makePDF($application_id)
    {

        // Create a new Pdf object with some global PDF options
        $pdf = new Pdf([
            'no-outline',         // Make Chrome not complain
            'margin-top'    => 0,
            'margin-right'  => 0,
            'margin-bottom' => 0,
            'margin-left'   => 0,

            // Default page options
            'disable-smart-shrinking'
        ]);

        $pdf->setOptions([
            'orientation' => 'landscape'
        ]);

        if (is_array($application_id)) {
            foreach($application_id as $single_application_id) {
                $application_detail = Badges::getApplication($single_application_id);
                $pdf->addPage(get_site_url() . '/wp-content/themes/sssc/certificate.php?application_id=' . $application_detail['uid']);
            }
        } else {
            $application_details = Badges::getApplication($application_id, true, 'application_id');
            $pdf->addPage(get_site_url() . '/wp-content/themes/sssc/certificate.php?application_id=' . $application_details['uid']);
        }

        $filepath = self::getUserUploadDirectory($application_details['account_id']);

        $filename = Badges::generateUniqueID() . '.pdf';

        $file_save_url = get_site_url() . '/' . str_replace(rtrim(ABSPATH, ','), '', Badges::getUserUploadDirectory()) . $filename;

        if (!$pdf->saveAs($filepath . $filename)) {
            echo $pdf->getError();
        }

        return $file_save_url;

    }

    /**
     * Badges::checkUserCode()
     *
     * Check users table for a match between email address
     * and time code.
     *
     * @param
     *
     * @return bool success
     */
    public static function checkUserCode($code, $email_address)
    {

        global $wpdb;

        $decoded_code = date('Y-m-d H:i:s', $code);

        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $query = "SELECT `id` FROM `" . $wpdb->prefix . "users`
                WHERE `user_email` = '" . $email_address ."'
                AND `user_registered` = '" . $decoded_code . "'";

        $result = $wpdb->get_results($query);

        if ((int)$wpdb->num_rows > 0) { // not sure why but have to cast this int to an int.
            return true;
        } else {
            return false;
        }

    }

    /**
     * Badges::getBadgeEvidence()
     *
     * Return Badge Application details
     *
     * @param $application_id ID of badge application to get evidence of
     *
     * @return Array $badge_evidence
     */
    public static function getBadgeEvidence($uid)
    {

        global $wpdb;

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_applications_evidence`
                    WHERE `application_id` = '" . $uid . "'";

        $badge_evidence = $wpdb->get_results($query, ARRAY_A);

        return $badge_evidence;

    }

    /**
     * Badges::badgesAwarded()
     *
     * Calculate and return number of awarded badges
     *
     * @param int $issuer_id
     * @return Array
     */
    public static function badgesAwarded()
    {
        global $wpdb;

        $query = "SELECT COUNT(`uid`) as `valid_applications` FROM `" . $wpdb->prefix . "badge_applications`
                    WHERE `application_status` = '1'";

        $result = $wpdb->get_row($query, ARRAY_A);


        return $result['valid_applications'];

    }

    /**
     * Badges::getIssuerPermissions()
     *
     * Get all categories assigned to a user.
     *
     * @param int $user_id
     * @return Array $result
     */
    public static function getIssuerPermissions($user_id)
    {

        global $wpdb;

        $result = [];

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_permissions`
                    WHERE `user_id` = '" . $user_id .  "'
                    AND `issuer_id` > 0";

        $issuer_ids = $wpdb->get_results($query, ARRAY_A);

        foreach ($issuer_ids as $issuer_id) {
            $result[] = $issuer_id['issuer_id'];
        }

        return $result;

    }

    /**
     * Badges::getCategoryPermissions()
     *
     * Get all categories assigned to a user.
     *
     * @param int $user_id
     * @return Array $result
     */
    public static function getCategoryPermissions($user_id)
    {

        global $wpdb;

        $result = [];

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_permissions`
                    WHERE `user_id` = '" . $user_id .  "'
                    AND `cat_id` > 0";

        $share_views = $wpdb->get_results($query, ARRAY_A);

        foreach ($share_views as $category_id) {
            $result[] = $category_id['cat_id'];
        }

        return $result;

    }

    /**
     * Badges::getTotalShareViews()
     *
     * Calculate and return total shared badges.
     *
     * @return Int $total_shares number of badges shared on social media
     */
    public static function getTotalShareViews()
    {

        global $wpdb;

        $result = [];

        $query = "SELECT * FROM `" . $wpdb->prefix . "postmeta`
                    WHERE `meta_key` = 'share_views'";

        $share_views = $wpdb->get_results($query, ARRAY_A);

        $total_shares = 0; // initialise to 0

        foreach ($share_views as $share_view) {
            $total_shares = $total_shares + $share_view['meta_value'];
        }

        return $total_shares;

    }

}
