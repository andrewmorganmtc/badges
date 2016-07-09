<?php

function badge_applications_update() {

    global $wpdb;
    global $current_user;
    global $form_data;

    if (empty($_REQUEST['id'])) {
        exit('This badge application is not valid');
    }

    // Update email address
    if (!empty($_REQUEST['update'])) {

        $form_data = [
            'email_address' => $_REQUEST['email_address'],
        ];

        $wpdb->update($wpdb->prefix . 'badge_applications', $form_data, [
           'id' => $_REQUEST['id'], // Where ID equals
        ]);

    }

    $application_details = Badges::getApplication($_REQUEST['id'], true);
    $badge_details =  Badges::getBadge($application_details['badge_id'], true);

    // Add comment
    if ($_REQUEST['action'] == 'add_comment') {

        $form_data = [
            'application_id' => $_REQUEST['id'],
            'author_id' => $current_user->data->ID,
            'text' => $_REQUEST['comment'],
        ];

        $application_data = [
            'application_status' => $_REQUEST['application_status'],
        ];

        switch ($_REQUEST['application_status']) {
            case '2' :
                $form_data['revoked_on'] = date("Y-m-d H:i:s");
                $application_data['revoked_on'] = date("Y-m-d H:i:s");
                break;
            case '1' :
                $form_data['approved_on'] = date("Y-m-d H:i:s");
                $application_data['approved_on'] = date("Y-m-d H:i:s");
                break;
        }

        $wpdb->insert($wpdb->prefix . 'badge_comments', $form_data);

        $wpdb->update($wpdb->prefix . 'badge_applications', $application_data, [
           'uid' => $_REQUEST['id'], // Where ID equals
        ]);

        Badges::applicationStatusEmail($_REQUEST['id'], $_REQUEST['application_status'], $_REQUEST['comment']);

    }

    $application_comments = Badges::getApplicationComments($_REQUEST['id']);
    $application_details = Badges::getApplication($_REQUEST['id'], true);

?>

<link type="text/css" href="<?= WP_PLUGIN_URL; ?>/sssc-badges/css/style_admin.css" rel="stylesheet" />

<div class="wrap">

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

    <div class="evidenceDetails">

        <div class="row">

            <h3>Badge Applied for</h3>

            <?= $badge_details['post_title'] ?>

        </div>

        <div class="row">

            <h3>Badge Applicant Email Address</h3>

            <a href="mailto:<?= $application_details['badge_applicant_email_address'] ?>">
                <?= $application_details['badge_applicant_email_address'] ?>
            </a>

        </div>

        <div class="row">

            <h3>Application Evidence</h3>

            <ul class="adminEvidence">

                <?php
                foreach ($application_details['evidence'] as $evidence) {
                    if ($evidence['type'] == 1) {
                    ?>
                    <li><?= $evidence['text'] ?></li>
                    <?php
                    } else {
                    ?>
                    <li><a href="<?= get_site_url() ?><?= $evidence['text'] ?>"><?= $evidence['text'] ?></a></li>
                    <?php

                    }
                }
                ?>

            </ul>

        </div>

        <div class="commentsWrap">
            <ul class="commentsList cf">
                <?php
                foreach ($application_comments as $application_comment) {

                    $author = get_userdata($application_comment['author_id']);
                ?>
                    <li class="cf">
                        <div class="innerText">
                            <?= $application_comment['text']; ?>
                        </div>

                        <div class="meta cf">
                            <div class="author">
                                <strong>
                                    <?= !empty($author->data->display_name) ? $author->data->display_name : $author->data->user_email; ?>
                                </strong>
                            </div>
                            <div class="date"><?= date('d/m/y h:i A', strtotime($application_comment['date'])) ?></div>
                        </div> <!-- .meta -->
                    </li>
                    <?php
                }
                ?>
            </ul>
        </div> <!-- .commentsWrap -->

        <form method="post" action="<?= $_SERVER['REQUEST_URI']; ?>" id="updateApplication">

            <h2>Email Feedback</h2>

            <div class="row">
                <textarea class="comment" name="comment"></textarea>
            </div>

            <?php
            if (current_user_can('manage_badges')) {
            ?>
                <div class="row">
                    <label for="application_status">Application status</label>
                    <select class="applicationStatus" name="application_status">
                        <option value="0"<?php
                        if ($application_details['application_status'] == 0) {
                        ?> selected<?php
                        }
                        ?>>Pending</option>
                        <option value="1"<?php
                        if ($application_details['application_status'] == 1) {
                        ?> selected<?php
                        }
                        ?>>Approved</option>
                        <option value="2"<?php
                        if ($application_details['application_status'] == 2) {
                        ?> selected<?php
                        }
                        ?>>Revoked</option>
                        <option value="3"<?php
                        if ($application_details['application_status'] == 3) {
                        ?> selected<?php
                        }
                        ?>>Declined</option>
                    </select>
                </div>
            <?php
            }
            ?>

            <input type="hidden" name="action" value="add_comment" />
            <input type="hidden" name="id" value="<?= $application_details['uid'] ?>" />
            <input type="submit" value="Save" class="button">
            <br />
            <br />

        </form>

        <div class="row">

            <h3>Date applied for</h3>

            <?= date('jS M Y H:i:s', strtotime($badge_details['post_date'])) ?>

        </div>

        <div class="row">

            <h3>Badge Introduction</h3>

            <?= $badge_details['badge_introduction'] ?>

        </div>

        <div class="row">

            <h3>Badge Summary</h3>

            <?= $badge_details['badge_summary'] ?>

        </div>

        <div class="row">

            <h3>Badge Criteria</h3>

            <?= $badge_details['badge_criteria'] ?>

        </div>

    </div> <!-- .evidenceDetails -->

    <a href="<?= admin_url('admin.php?page=badge_applications_list_menu') ?>" class="button">Back to Applications list</a>

</div>
<?php
}

