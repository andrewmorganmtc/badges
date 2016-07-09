<?php
function badge_applications_create () {

$errors = [];

if(!empty ($_REQUEST['insert']) && $_REQUEST['insert'] == 'Save') {
	global $wpdb;

    $existing_users_applying = [];
    $no_username_emails_to_add = [];
    $evidence = [];

    if (empty(array_filter($_REQUEST['apply_evidence']))) {
        $errors['empty_evidence'] = 'Please add some evidence supporting the application of this badge.';
    }

    if (empty($_REQUEST['existing_user_emails']) && empty($_REQUEST['new_user_emails'])) {
        $errors[] = 'Please select an existing user or add new email addresses to add users';
    }

    if (empty($errors)) {

        foreach ($_REQUEST['apply_evidence'] as $textarea) {
            $evidence[] = [
                'type' => 1,
                'text' => $textarea,
            ];
        }

        if (!empty($_REQUEST['badge_id']) && is_numeric($_REQUEST['badge_id'])) {
            $badge_to_apply_for = (int)$_REQUEST['badge_id'];
            $badge_details = Badges::getBadge($badge_to_apply_for);
        } else {
            $badge_to_apply_for = null;
        }

        if (!empty($_REQUEST['existing_user_emails'])) {
            foreach ($_REQUEST['existing_user_emails'] as $user_id) {
                // Get user details
                $user_details = get_userdata($user_id);

                if (Badges::userHasBadge($user_details->data->ID, $badge_to_apply_for)) {
                    $errors[] = 'Existing user with the email address ' . $user_details->data->user_email . ' has already applied for this badge';
                } else {
                    $application_id = Badges::createApplication($user_id, $badge_to_apply_for, true);

                    if (!empty($evidence)) {
                        foreach ($evidence as $evidence_item) {
                            $evidence_item['application_id'] = $application_id;
                            $wpdb->insert($wpdb->prefix . 'badge_applications_evidence', $evidence_item);
                        }
                    }

                    $success[] = $user_details->data->user_email . ' applied to ' . $badge_details['post_title'];
                }
            }
        }

        if (!empty($_REQUEST['new_user_emails'])) {

            $new_user_emails = array_filter(explode(PHP_EOL, $_REQUEST['new_user_emails']));

            foreach ($new_user_emails as $new_user_email) {

                $new_user_email = trim($new_user_email);

                // Start by checking the email address is valid
                if (filter_var($new_user_email, FILTER_VALIDATE_EMAIL)) {

                    // Try and resolve email address to member ID
                    $existing_user_id = get_user_by_email($new_user_email)->ID;

                    // Check if existing user ID returned a value
                    if (!empty($existing_user_id)) {
                        // Check if user already has the badge
                        if (Badges::userHasBadge($existing_user_id, $badge_to_apply_for)) {
                            $errors[] = $new_user_email . ' already has applied for this badge';
                        } else {
                            $existing_user_emails_to_add[] = $new_user_email;
                        }

                    } else {
                        $no_username_emails_to_add[] = $new_user_email;
                    }

                } else {
                    $errors[] = $new_user_email . ' is not a valid email address';
                }
            }

            if (!empty($no_username_emails_to_add)) {
                foreach($no_username_emails_to_add as $email_to_add) {

                    $new_user_id = Badges::addUserViaEmail($email_to_add);

                    if (!empty($new_user_id)) {
                        $new_application_id = Badges::createApplication($new_user_id, $badge_to_apply_for, true);

                        if (!empty($evidence)) {
                            foreach ($evidence as $evidence_item) {
                                $evidence_item['application_id'] = $new_application_id;
                                $wpdb->insert($wpdb->prefix . 'badge_applications_evidence', $evidence_item);
                            }
                        }

                        $success[] = $email_to_add . " has been notified of the badge award and a new user account has been created";
                    }
                }
            }

            if (!empty($existing_user_emails_to_add)) {
                foreach ($existing_user_emails_to_add as $existing_user_email) {
                    $new_application_id = Badges::createApplication($existing_user_id, $badge_to_apply_for, true);

                    if (!empty($evidence)) {
                        foreach ($evidence as $evidence_item) {
                            $evidence_item['application_id'] = $new_application_id;
                            $wpdb->insert($wpdb->prefix . 'badge_applications_evidence', $evidence_item);
                        }
                    }

                    $success[] = $existing_user_email . ' applied to ' . $badge_details['post_title'];
                }
            }

        }

        if ((!empty($_REQUEST['badge_id']) && is_numeric($_REQUEST['badge_id'])) && (is_numeric($_REQUEST['user']) && !empty($_REQUEST['user']))) {
            $existing_users_applying[] = $_REQUEST['user'];
        }
    }
}

$all_badge_users = Badges::getUsers();

?>
<link type="text/css" href="<?= WP_PLUGIN_URL; ?>/sssc-badges/css/style_admin.css" rel="stylesheet" />

<div class="wrap">

    <h2>Add New Applications</h2>

    <?php
    if (!empty($success)) {
    ?>
        <ul class="success">
            <?php
            foreach ($success as $success) {
            ?>
                <li>
                    <?= $success ?>
                </li>
            <?php
            }
            ?>
        </ul>
    <?php
    }
    ?>

    <?php
    if (!empty($errors)) {
    ?>
        <ul class="error">
            <?php
            foreach ($errors as $error) {
            ?>
                <li>
                    <?= $error ?>
                </li>
            <?php
            }
            ?>
        </ul>
    <?php
    }
    ?>

    <form method="post" action="<?= $_SERVER['REQUEST_URI']; ?>">

        <div class="row">
            <label for="existing_user_emails">Choose from existing badge users (Hold ctrl or cmd for multiple selections)</label>

            <select name="existing_user_emails[]" id="existing_user_emails" style="height: 350px;" multiple>
                <?php

                foreach($all_badge_users as $user_id) {
                    $user_meta = get_userdata($user_id['id']);
                ?>
                    <option value="<?= $user_meta->data->ID ?>"<?php
                        if (!empty($_REQUEST['existing_user_emails']) && in_array($user_meta->data->ID, $_REQUEST['existing_user_emails'])) {
                            echo ' selected';
                        }
                        ?>>

                        <?= $user_meta->data->display_name ?> (<?= $user_meta->data->user_email ?>)

                    </option>
                <?php
                }
                ?>
            </select>

        </div>

        <div class="row">
            <label for="newUserEmails">Add new users <em>(One email address per line)</em></label>
            <textarea name="new_user_emails" placeholder="Enter email addresses here" id="newUserEmails"><?= $_REQUEST['new_user_emails'] ?></textarea>
        </div>

        <div class="row">
            <label for="badgeID">Badge Applied for</label>

            <?php
            $all_badges = Badges::get();
            if (!empty($all_badges)) {
            ?>

                <select name="badge_id" id="badgeID">
                    <?php
                    foreach ($all_badges as $badge) {
                    ?>
                        <option value="<?= $badge->ID ?>"<?php
                            if ($badge->ID == $_REQUEST['badge_id']) {
                                echo ' selected';
                            }
                        ?>>
                            <?= $badge->post_title ?>
                        </option>
                    <?php
                    }
                    ?>
                </select>

            <?php
            }
            ?>
        </div>

        <div class="row">
            <label for="applyEvidence">Evidence</label>
            <?php

            if (empty($_REQUEST['apply_evidence'])) {
                $_REQUEST['apply_evidence'][] = 'No evidence required';
            }

            foreach($_REQUEST['apply_evidence'] as $request_evidence) {
            ?>
                <textarea rows="20" type="text" id="applyEvidence" name="apply_evidence[]" placeholder="Please paste your evidence here" /><?= $request_evidence ?></textarea>
            <?php
            }
            ?>
        </div>

        <input type='submit' name="insert" value='Save' class='button'>

    </form>
</div>
<?php
}
