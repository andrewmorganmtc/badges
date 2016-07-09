<?php
/**
 * Plugin Name: SSSC Badges
 * Description: Administer Badges for SSSC
 * Author: Andrew Morgan | mtc. <andrew.morgan@mtcmedia.co.uk>
 * Author URI: http://www.mtcmedia.co.uk/
 */

require_once ABSPATH . 'vendor/autoload.php';

define('SSSC_BADGES_ROOTDIR', plugin_dir_path(__FILE__));
require_once SSSC_BADGES_ROOTDIR . 'Classes/Badges.php';

// Badge Applications
require_once SSSC_BADGES_ROOTDIR . 'applications/badge_applications.php';

// Badge Issuers
require_once SSSC_BADGES_ROOTDIR . 'badge_issuers/badge_issuers.php';

// Dont show admin bar
show_admin_bar(false);

/**
 * Registration
 *
 * Allows user to register through the front end form
 * which should be found at /register-for-badges/
 *
 */

function front_end_register_action() {

    if ($_REQUEST['action'] == 'front_end_register_action') {

        global $form_details;
        global $errors;

        $form_details = [
            'register_first_name' => sanitize_text_field($_REQUEST['register_first_name']),
            'register_last_name' => sanitize_text_field($_REQUEST['register_last_name']),
            'register_job_title' => sanitize_text_field($_REQUEST['register_job_title']),
            'register_organisation' => sanitize_text_field($_REQUEST['register_organisation']),
            'register_email_address' => sanitize_text_field($_REQUEST['register_email_address']),
        ];

        // Check if the username already exists
        if (empty($form_details['register_first_name'])) {
            $form_details['register_errors']['register_first_name'] = 'Please fill in your first name';
        }

        // Check if the username already exists
        if (empty($form_details['register_last_name'])) {
            $form_details['register_errors']['register_last_name'] = 'Please fill in your last name';
        }

        // Check if the username already exists
        if (empty($form_details['register_email_address']) && (!filter_var($form_details['register_email_address'], FILTER_VALIDATE_EMAIL))) {
            $form_details['register_errors']['username_exists'] = 'Please fill in a valid email address... this will be used as your username to sign in later';
        } elseif (username_exists($form_details['register_email_address']) || email_exists($form_details['register_email_address'])) {
            $form_details['register_errors']['username_exists'] = 'This email address already exists as a member account.  Have you <a href="#">Forgotten your password</a>';
        }

        // Check if password is not empty and matches confirm password field
        if (empty($_REQUEST['register_password']) || empty($_REQUEST['register_password_confirm'])) {
            $form_details['register_errors']['register_password_error'] = 'Please fill in both password fields';
        } elseif ($_REQUEST['register_password'] !== $_REQUEST['register_password_confirm']) {
            $form_details['register_errors']['register_password_confirm_error'] = 'The passwords do not match.  Please check your password';
        } elseif (strlen($_REQUEST['register_password']) < 6) {
            $form_details['register_errors']['register_password_length_error'] = 'The password needs to be longer than 5 characters';
        }

        if (empty($form_details['register_errors'])) {

            $user_details = [
                'user_pass' => $_REQUEST['register_password'],
                'user_login' => $form_details['register_email_address'],
                'user_email' => $form_details['register_email_address'],
                'first_name' => $form_details['register_first_name'],
                'last_name' => $form_details['register_last_name'],
                'user_registered' => date("Y-m-d H:i:s"),
            ];

            $user = wp_insert_user($user_details);

            // Redirect if no errors in username insertion
            if (!is_wp_error($user)) {

                if (is_numeric($user)) { // Should be USER ID if no errors
                    add_user_meta( $user, 'user_job_title', $form_details['register_job_title']);
                    add_user_meta( $user, 'user_organisation', $form_details['register_organisation']);
                }

                // Log user in
                $login_details = [
                    'user_login' => $form_details['register_email_address'],
                    'user_password' => $_REQUEST['register_password'],
                    'remember' => false, // limited use
                ];

                $login = wp_signon($login_details);

                if (!is_wp_error($login)) {
                    header('Location: /members?new_user=true');
                    exit();
                }

            } else {
                $errors['user_register'] = 'There was a problem adding you as a user... please try again';
            }
        }
    }
}

add_action('init', 'front_end_register_action');

function badge_bulk_options() {

    if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'badge_bulk_options') {

        global $errors;

        if (!is_array($_REQUEST['applications_bulk_options_array'])) {
            $errors['no_badges_selected'] = 'You need to select a badge to perform actions on it.';
        }

        if (empty($errors)) {

            if (!empty($_REQUEST['applications_bulk_options_array']) && !empty($_REQUEST['bulk_options']) && $_REQUEST['bulk_options'] == 'pdf') {

                global $file_save_url;

                $file_save_url = Badges::makePDF($_REQUEST['applications_bulk_options_array']);

            }

            if ($_REQUEST['bulk_options'] == 'download' && !empty($_REQUEST['applications_bulk_options_array'])) {

                global $file_save_url;

                $download_folder_path = Badges::getUserUploadDirectory();

                $unique_download_filename = Badges::generateUniqueID() . '.zip';

                $zip_file_save_path = $download_folder_path . $unique_download_filename;

                $file_save_url = get_site_url() . '/' . str_replace(rtrim(ABSPATH, ','), '', $zip_file_save_path);

                $badge_filenames = Badges::getBadgeFilenames($_REQUEST['applications_bulk_options_array']);

                Badges::createZip($badge_filenames, $zip_file_save_path, $download_folder_path);

            }

        }
    }

}

add_action('init', 'badge_bulk_options');

function make_pdf() {

    if ((!empty($_REQUEST['application_id']) && is_numeric($_REQUEST['application_id'])) && (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'download_pdf')) {

        global $file_save_url;

        $file_save_url = Badges::makePDF($_REQUEST['application_id']);

    }

}

add_action('init', 'make_pdf');

function get_account_update_parameters() {

    if ($_REQUEST['action'] == 'account_update') {

        global $account_update_form;
        global $errors;
        global $current_user;
        global $success_message;

        $user = wp_get_current_user();

        if (strlen($_REQUEST['display_name']) < 5) {
            $errors['display_name_length'] = 'Your name needs to have more than 4 characters';
        }

        if (!filter_var($_REQUEST['user_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email_address_not_valid'] = 'Please enter a valid email address';
        }

        if (Badges::userEmailExists($_REQUEST['user_email']) && $_REQUEST['user_email'] !== $current_user->data->user_email) {
            $errors['email_address_exists'] = 'This email address is already registered in the system';
        }

        if (empty($_REQUEST['user_job_title'])) {
            $errors['user_job_title'] = 'Please add a job title to your account';
        }

        if (empty($_REQUEST['user_organisation'])) {
            $errors['user_organisation'] = 'Please add your organisation to your account';
        }

        if (empty($errors)) {

            $account_update_form = [
                'ID' => $user->data->ID,
                'display_name' => sanitize_text_field($_REQUEST['display_name']),
                'user_email' => sanitize_text_field($_REQUEST['user_email']),
            ];

            if (!empty($_REQUEST['password'])) {
                if (strlen($_REQUEST['password'] > 5)) {
                    wp_set_password($_REQUEST['password'], $user->data->ID);
                } else {
                    $errors['password_errors'] = 'Your password was not valid.';
                }
            }

            $update_user = wp_update_user($account_update_form);

            // Get the current user meta fields (true returns value instead of array)
            $job_title_previous_meta_value = get_user_meta($user->data->ID, 'user_job_title', true);
            $user_organisation_previous_meta_value = get_user_meta($user->data->ID, 'user_organisation', true);

            // Update the user meta fields comparing to previously set values to avoid duplication of keys
            update_user_meta($user->data->ID, 'user_job_title', $_REQUEST['user_job_title'], $job_title_previous_meta_value);
            update_user_meta($user->data->ID, 'user_organisation', $_REQUEST['user_organisation'], $user_organisation_previous_meta_value);

            // Clean user cache for any changes
            clean_user_cache($user);

            if (!is_wp_error($update_user)) {
                $success_message = 'User updated';
            } else {
                $errors = $update_user->get_error_codes();
            }

            // If email address is updated... call method to update all references to this email address
            if ($_REQUEST['user_email'] !== $current_user->data->user_email) {
                Badges::changeEmailAddress($current_user->data->user_email, $_REQUEST['user_email']);
            }

            $success_message = 'Your account has been updated';

        }
    }

}

add_action('after_setup_theme', 'get_account_update_parameters');

/**
 * Login for front end
 *
 * Allows user to sign in through the front end form
 * found at /register-for-badges/
 *
 */

function front_end_login() {

    if ($_REQUEST['action'] == 'front_end_login_action') {

        global $form_details;

        $options = [
            'user_login' => $_REQUEST['login_email_address'],
            'user_password' => $_REQUEST['login_password'],
            'remember' => $_REQUEST['login_remember'],
        ];

        $user = wp_signon($options, false);

        if (!is_wp_error($user) && !empty($_REQUEST['redirect'])) {
            header('Location: ' . $_REQUEST['redirect']);
            exit();
        } else if (!is_wp_error($user)) {
            header('Location: /members');
            exit();
        }

        // If not redirected yet return form submission and errors to page.
        $form_details['sign_in_errors'] = $user->get_error_messages();
        return $form_details;

    }
}

add_action('after_setup_theme', 'front_end_login', 12);

/**
 * Apply for badge
 */

function apply_for_badge() {

    global $current_user;

    if ($_REQUEST['action'] == 'apply_for_badge' && $current_user instanceof WP_User) {

        global $form_details;
        global $wpdb;
        global $errors;

        $evidence = [];
        $errors = [];

        // only add new application when user is signed in

        if (!empty($_FILES['apply_attachment']['name'][0])) { // check for a name being present in first index
            $files_array = Badges::filesArrayCleanup($_FILES['apply_attachment']);
        } else {
            $files_array = [];
        }

        $allowed_mime_types = [
            'application/pdf', // .pdf
            'image/png', // .png
            'text/plain', // .txt
            'application/msword', // .doc
            'application/x-mswrite', // .docx
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/octet-stream', // .zip
        ];

        $allowed_extensions = [
            'pdf',
            'doc',
            'docx',
            'txt',
            'zip',
        ];

        $form_details = [
            'apply_evidence' => $_REQUEST['apply_evidence'],
            'apply_attachment' => $files_array,
        ];

        foreach ($form_details['apply_evidence'] as $textarea) {
            if (!empty($textarea)) {
                $evidence[] = [
                    'type' => 1,
                    'text' => $textarea,
                ];
            }
        }

        $upload_directory = str_replace(rtrim(ABSPATH, '/'), '', wp_upload_dir()['path']);

        if (!file_exists($upload_directory)) {
            wp_mkdir_p($upload_directory);
        }

        if (!empty($files_array)) {

            foreach ($files_array as $attachment) {

                // validation checking
                if (in_array($attachment['type'], $allowed_mime_types) && in_array(pathinfo($attachment['name'], PATHINFO_EXTENSION), $allowed_extensions)) {

                    $file_name = time() . str_replace(' ', '_', $attachment['name']);

                    if (move_uploaded_file($attachment['tmp_name'], wp_upload_dir()['path'] . '/' . $file_name)) {

                        $evidence[] = [
                            'type' => 2,
                            'text' => $upload_directory . '/' . $file_name,
                        ];

                    }

                } else {
                    $errors[$attachment['name']] = 'We do not accept this type of file - ' . $attachment['name'];
                }
            }

        }

    /*
     * Make sure some evidence has been provided (textarea or attachment)
     */
    if (!empty($evidence)) {

        $badge_details = Badges::getBadge($_REQUEST['badge_id']);

        if (!empty($badge_details['badge_image_path'])) {

            if (empty($_REQUEST['update_application'])) {

                // Insert details into database
                $form_inputs = [
                    'application_id' => Badges::generateUniqueID(),
                    'badge_id' => (int)$_REQUEST['badge_id'],
                    'baked_badge_image' => $badge_details['badge_image_path'],
                    'badge_applicant_email_address' => (string)$_REQUEST['badge_applicant_email_address'],
                    'account_id' => (int)$_REQUEST['account_id'],
                    'application_status' => 0,
                    'created_on' => date("Y-m-d H:i:s"),
                    'salt' => Badges::generateUniqueID(),
                ];

                $result = $wpdb->insert($wpdb->prefix . 'badge_applications', $form_inputs);

                $application_insert_id = $wpdb->insert_id;

            } else {
                $application_insert_id = $_REQUEST['update_application'];
            }

            if (!empty($evidence)) {
                foreach ($evidence as $evidence_item) {
                    $evidence_item['application_id'] = $application_insert_id;
                    $wpdb->insert($wpdb->prefix . 'badge_applications_evidence', $evidence_item);
                }
            }

            $form_details['success'] = true;

        } else {
            $errors['no_image_present'] = 'You cannot apply for this badge as it does not have a valid image.';
        }

    }

    } else {
        $errors['evidence_error'] = 'Please provide evidence for your application';
    }
}

add_action('init', 'apply_for_badge');

/*
 * Remove private: etc. from being added to page title
 */
function title_format($content) {
    return '%s';
}

add_filter('private_title_format', 'title_format');
add_filter('protected_title_format', 'title_format');

function badgesPostType() {

    $labels = [
        'name'                => _x( 'Badges', 'Post Type General Name', 'badges-page-text-domain' ),
        'singular_name'       => _x( 'Badge', 'Post Type Singular Name', 'badges-page-text-domain' ),
        'menu_name'           => __( 'Badges', 'badges-page-text-domain' ),
        'name_admin_bar'      => __( 'Post Type', 'badges-page-text-domain' ),
        'parent_item_colon'   => __( 'Parent:', 'badges-page-text-domain' ),
        'all_items'           => __( 'All Badges', 'badges-page-text-domain' ),
        'add_new_item'        => __( 'Add New Badge', 'badges-page-text-domain' ),
        'add_new'             => __( 'New Badge', 'badges-page-text-domain' ),
        'new_item'            => __( 'New Badge', 'badges-page-text-domain' ),
        'edit_item'           => __( 'Edit Badge', 'badges-page-text-domain' ),
        'update_item'         => __( 'Update Badge', 'badges-page-text-domain' ),
        'view_item'           => __( 'View Badge', 'badges-page-text-domain' ),
        'search_items'        => __( 'Search Badges', 'badges-page-text-domain' ),
        'not_found'           => __( 'No Badges found', 'badges-page-text-domain' ),
        'not_found_in_trash'  => __( 'No Badges found in Trash', 'badges-page-text-domain' ),
    ];

    $args = [
        'label'               => __( 'Badge', 'badges-page-text-domain' ),
        'description'         => __( 'Badge Listing', 'badges-page-text-domain' ),
        'labels'              => $labels,
        'supports'            => array(),
        'taxonomies'          => array('category'),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 2,
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
    ];

    register_post_type('badges', $args);

}

add_action('init', 'badgesPostType', 0);

/*
 * Custom rewrite rule for badge applications
 */

function add_my_var($public_query_vars) {
    $public_query_vars[] = 'application_id';
    $public_query_vars[] = 'new_application_email';
    $public_query_vars[] = 'new_application_code';
    $public_query_vars[] = 'new_user';
    $public_query_vars[] = 'issuers';
    return $public_query_vars;
}

add_filter('query_vars', 'add_my_var');

function badges_plugin_activate() {

    remove_role('badges_user');
    remove_role('badges_admin');
    remove_role('editor');
    remove_role('contributor');
    remove_role('subscriber');
    remove_role('secure_client');
    remove_role('author');

    // Create a user role with only limited dashboard functionality
    $result = add_role( 'badges_user', __('Badge User' ),
        [
            'read' => true, // true allows this capability
            'edit_posts' => false, // Allows user to edit their own posts
            'edit_pages' => false, // Allows user to edit pages
            'edit_others_posts' => false, // Allows user to edit others posts not just their own
            'create_posts' => false, // Allows user to create new posts
            'manage_categories' => false, // Allows user to manage post categories
            'publish_posts' => false, // Allows the user to publish, otherwise posts stays in draft mode
            'edit_themes' => false, // false denies this capability. User can’t edit your theme
            'install_plugins' => false, // User cant add new plugins
            'update_plugin' => false, // User can’t update any plugins
            'update_core' => false // user cant perform core updates
        ]
    );

    $result = add_role( 'badges_admin', __('Badge Admin' ),
        [
            'read' => true, // true allows this capability
            'edit_posts' => true, // Allows user to edit their own posts
            'edit_pages' => false, // Allows user to edit pages
            'edit_others_posts' => true, // Allows user to edit others posts not just their own
            'create_posts' => true, // Allows user to create new posts
            'manage_categories' => false, // Allows user to manage post categories
            'publish_posts' => true, // Allows the user to publish, otherwise posts stays in draft mode
            'edit_themes' => false, // false denies this capability. User can’t edit your theme
            'install_plugins' => false, // User cant add new plugins
            'update_plugin' => false, // User can’t update any plugins
            'update_core' => false, // user cant perform core updates
            'manage_badges' => true, // Add new capability for managing badges
        ]
    );

    // Add manage badges to administrator and editor
    $role = get_role('administrator');
    $role->add_cap('manage_badges');
    $role->add_cap('manage_issuers');


    $role = get_role('badges_admin');
    $role->add_cap('manage_badges');
    $role->add_cap('edit_published_posts');
    $role->add_cap('read_private_posts');
    $role->add_cap('upload_files');

    // update badges_user as default role
    update_option('default_role', 'badges_user');

}

register_activation_hook( __FILE__, 'badges_plugin_activate');

function get_new_user() {

    global $wpdb;
    global $form_details;

    if (!empty($_REQUEST['new_application_email']) && !empty($_REQUEST['new_member_code'])) {


        // Log out any signed in user as this link has been clicked by someone claiming a username
        if (is_user_logged_in()) {
            wp_logout();
        }

        // Check if the email exists and the code matches a valid user name
        if (Badges::userEmailExists($_REQUEST['new_application_email']) && Badges::checkUserCode($_REQUEST['new_member_code'], $_REQUEST['new_application_email'])) {
            $form_details = [
                'new_application_email' => sanitize_text_field($_REQUEST['new_application_email']),
                'new_member_code' => sanitize_text_field($_REQUEST['new_member_code']),
            ];
        }
    }
}

add_action('after_setup_theme', 'get_new_user');

function new_account_update() {

    if ($_REQUEST['action'] == 'account_update_new_user') {

        global $account_update_form;
        global $errors;
        global $success_message;
        global $form_details;

        $form_details = [
            'new_application_email' => sanitize_text_field($_REQUEST['new_application_email']),
            'new_member_code' => sanitize_text_field($_REQUEST['new_member_code']),
            'display_name' => sanitize_text_field($_REQUEST['display_name']),
            'user_job_title' => sanitize_text_field($_REQUEST['user_job_title']),
            'user_organisation' => sanitize_text_field($_REQUEST['user_organisation']),
        ];

        if (strlen($form_details['display_name']) < 5) {
            $errors['display_name_length'] = 'Your name needs to have more than 4 characters';
        }

        if (empty($form_details['user_job_title'])) {
            $errors['user_job_title'] = 'Please add a job title to your account';
        }

        if (empty($form_details['user_organisation'])) {
            $errors['user_organisation'] = 'Please add your organisation to your account';
        }

        if (empty($_REQUEST['user_pass'])) {
            $errors['user_pass'] = 'Please add a password';
        } elseif (empty($_REQUEST['confirm_user_pass'])) {
            $errors['confirm_user_pass'] = 'Please confirm your password';
        } elseif (strlen($_REQUEST['password'] > 5)) {
            $errors['confirm_user_pass'] = 'Please confirm your password';
        } elseif ($_REQUEST['user_pass'] !== $_REQUEST['confirm_user_pass']) {
            $errors['user_passwords_not_matching'] = 'Your passwords do not match';
        }

        if (empty($errors)) {

            // Get the user details by email
            $user = get_user_by('email', $form_details['new_application_email']);

            // Set the new password
            wp_set_password($_REQUEST['user_pass'], $user->data->ID);

            $account_update_form = [
                'ID' => $user->data->ID,
                'display_name' => $form_details['display_name'],
            ];

            $update_user = wp_update_user($account_update_form);

            // Get the current user meta fields (true returns value instead of array)
            $job_title_previous_meta_value = get_user_meta($user->data->ID, 'user_job_title', true);
            $user_organisation_previous_meta_value = get_user_meta($user->data->ID, 'user_organisation', true);

            // Update the user meta fields comparing to previously set values to avoid duplication of keys
            update_user_meta($user->data->ID, 'user_job_title', $form_details['user_job_title'], $job_title_previous_meta_value);
            update_user_meta($user->data->ID, 'user_organisation', $form_details['user_organisation'], $user_organisation_previous_meta_value);

            // Clean user cache for any changes
            clean_user_cache($user);

            // Log user in
            $login_details = [
                'user_login' => $_REQUEST['new_application_email'],
                'user_password' => $_REQUEST['user_pass'],
                'remember' => false, // limited use
            ];

            $login = wp_signon($login_details);

            if (!is_wp_error($login)) {
                header('Location: /members?new_user=true');
                exit();
            }

            $success_message = 'Your account has been updated';

        }
    }

}

add_action('after_setup_theme', 'new_account_update');

function select_issuers($user) {

    global $wpdb;
    global $current_user;

    if (current_user_can('manage_issuers')) {

        $query = "SELECT * FROM `" . $wpdb->prefix . "badge_issuers`";

        $badge_issuers = $wpdb->get_results($query, ARRAY_A);

        $user_issuer_list = Badges::getIssuerPermissions($current_user->data->ID);

    ?>

        <h3>Badge Issuer Access</h3>

        <table class="form-table">

            <tr>

                <th>
                    <label for="issuers">Issuers</label>
                </th>

                <td>
                    <select name="issuers[]"
                        id="issuers"
                        style="width: 100%; min-height:200px; max-width: 500px;"
                        multiple />
                            <?php
                            if (!empty($badge_issuers)) {
                                foreach ($badge_issuers as $badge_issuer) {
                                ?>
                                    <option
                                        value="<?= $badge_issuer['id'] ?>"<?php
                                        if (in_array($badge_issuer['id'], $user_issuer_list)) {
                                            echo 'selected';
                                        } ?>><?= $badge_issuer['name'] ?></option>
                                <?php
                                }
                            }
                            ?>
                    </select>
                </td>

            </tr>

        </table>

    <?php
    }
}


add_action('show_user_profile', 'select_issuers');
add_action('edit_user_profile', 'select_issuers');


function select_user_badge_categories($user) {

    global $wpdb;
    global $current_user;


    if (current_user_can('manage_issuers')) {

        $categories = get_categories([
            'type' => 'badges'
        ]);

        $category_list = Badges::getCategoryPermissions($current_user->data->ID);

    ?>

        <h3>Badge Category Access</h3>

        <table class="form-table">

            <tr>

                <th>
                    <label for="category_list">Categories</label>
                </th>

                <td>
                    <select name="category_list[]"
                        id="category_list"
                        style="width: 100%; min-height:200px; max-width: 500px;"
                        multiple />
                            <?php
                            if (!empty($categories)) {
                                foreach ($categories as $category) {
                                ?>
                                    <option value="<?= $category->term_id ?>"<?php
                                        if (in_array($category->term_id, $category_list)) {
                                            echo 'selected';
                                        }
                                    ?>><?= $category->name ?></option>
                                <?php
                                }
                            }
                            ?>
                    </select>
                </td>

            </tr>

        </table>

    <?php
    }
}

add_action('show_user_profile', 'select_user_badge_categories');
add_action('edit_user_profile', 'select_user_badge_categories');


function update_extra_user_profile_fields($user) {

    global $wpdb;

    if (is_object($user)) {
        $user_id = $user->data->ID;
    } else {
        $user_id = $user;
    }

    if (!empty($_REQUEST['issuers'])) {

        $query = "DELETE FROM `" . $wpdb->prefix . "badge_permissions`
                        WHERE `user_id` = '" . $user_id . "'
                        AND `issuer_id` > 0";

        $wpdb->query($query); // Delete existing before readding.

        foreach ($_REQUEST['issuers'] as $issuer_id) {
            $wpdb->insert($wpdb->prefix . 'badge_permissions', [
                'issuer_id' => $issuer_id,
                'user_id' => $user_id,
            ]);
        }

    }

    if (!empty($_REQUEST['category_list'])) {

        $query = "DELETE FROM `" . $wpdb->prefix . "badge_permissions`
                        WHERE `user_id` = '" . $user_id . "'
                        AND `cat_id` > 0";

        $wpdb->query($query); // Delete existing before readding.

        foreach ($_REQUEST['category_list'] as $category_id) {
            $wpdb->insert($wpdb->prefix . 'badge_permissions', [
                'cat_id' => $category_id,
                'user_id' => $user_id,
            ]);
        }

    }

}

add_action('personal_options_update', 'update_extra_user_profile_fields');
add_action('edit_user_profile_update', 'update_extra_user_profile_fields');

function acf_add_select_choices($field) {

    global $wpdb;
    global $current_user;

    $query = "SELECT * FROM `" . $wpdb->prefix . "badge_issuers`";

    /*
     * Narrow down issuer list for users that do not have full access
     */
    if (current_user_can('manage_issuers')) {

        $issuer_list = Badges::getIssuerPermissions($current_user->data->ID);

        if (!empty($issuer_list)) {

            $issuer_list = implode(', ', $issuer_list);

            $query .= " WHERE `id` IN ('" . $issuer_list . "')";

        }
    }

    $badge_issuers = $wpdb->get_results($query, ARRAY_A);

    // reset choices
    $field['choices'] = [];

    foreach ($badge_issuers as $badge_issuer) {
        $field['choices'][$badge_issuer['id']] = $badge_issuer['name'];
    }

    return $field;

}

add_filter('acf/load_field/name=badge_issuer', 'acf_add_select_choices');

/*
 * Filter views based on permission level
 */
function filter_manage_posts_page($query) {

    if (is_admin()) {

        //execute only on the 'post' content type
        global $post_type;
        global $current_user;

        /*
         * If current user is not able to manage issuers and has access to
         * the badges admin dashboard they will be a badge issuer only and
         * will have the badges list filtered to only the badges they have
         * control over.
         */


        if ($post_type == 'badges' && !current_user_can('manage_issuers') && $_SERVER['PHP_SELF'] == '/wp-admin/edit.php') {

            $user_issuer_list = get_user_meta($current_user->data->ID, 'issuers', false);

            $comma_seperated_user_issuer_list = trim(implode(', ', $user_issuer_list));

            $query->query_vars = [
                'post_type' => 'badges',
                'meta_key' => 'badge_issuer',
                'meta_value' => $user_issuer_list,
                'meta_compare' => 'IN',
            ];

        }

    }
}

add_filter('pre_get_posts', 'filter_manage_posts_page');

function add_front_end_comment() {

    global $wpdb;

    // Add comment
    if (!empty($_REQUEST['add_comment']) && $_REQUEST['add_comment'] == true) {

        $form_data = [
            'application_id' => $_REQUEST['id'],
            'author_id' => $_REQUEST['author_id'],
            'text' => $_REQUEST['comment'],
            'date' => date("Y-m-d H:i:s"),
        ];

        $result = $wpdb->insert($wpdb->prefix . 'badge_comments', $form_data);

        if (!empty($_REQUEST['application_status'])) {
            $application_data = [
                'application_status' => $_REQUEST['application_status'],
            ];

            $wpdb->update($wpdb->prefix . 'badge_applications', $application_data, [
                'uid' => $_REQUEST['application_uid'], // Where ID equals
            ]);

            Badges::applicationStatusEmail($_REQUEST['id'], $_REQUEST['application_status'], $_REQUEST['comment']);

        }

        if ($result == true) {
            $form_data['success'] = true;
            $form_data['author'] = get_the_author_meta('display_name', $form_data['author_id']);
            exit(json_encode($form_data));
        }

    }
}

add_action('init', 'add_front_end_comment');
