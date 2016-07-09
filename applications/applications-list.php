<?php
function badge_applications_list () {

    global $wpdb;

    if (!empty($_REQUEST['bulk_options']) && is_array($_REQUEST['bulk_actions'])) {

        if ($_REQUEST['bulk_options'] == 'approve') {
            $application_status = 1;
            $message = 'Applications Approved';
        } elseif ($_REQUEST['bulk_options'] == 'revoke') {
            $application_status = 2;
            $message = 'Applications Revoked';
        } else {
            $application_status = 0;
        }

        foreach ($_REQUEST['bulk_actions'] as $application_id) {

            if (is_numeric($application_id)) {

                $result = $wpdb->update(
                    $wpdb->prefix . 'badge_applications', [
                        'application_status' => $application_status,
                        'approved_on' => date("Y-m-d H:i:s"),
                    ], [
                        'uid' => $application_id
                    ]
                );

                Badges::applicationStatusEmail($application_id, $application_status);

            }

        }

    } elseif (!empty($_REQUEST['approve']) && $_REQUEST['approve'] == 'true') {

        $result = $wpdb->update(
            $wpdb->prefix . 'badge_applications', [
                'application_status' => 1,
                'approved_on' => date("Y-m-d H:i:s"),
            ], [
                'uid' => $_REQUEST['id']
            ]
        );

        Badges::applicationStatusEmail($_REQUEST['id'], 1);

        $message = 'Application has been approved';

    } elseif (!empty($_REQUEST['revoke'])) {

        $wpdb->update(
            $wpdb->prefix . 'badge_applications', [
                'application_status' => 2,
                'revoked_on' => date("Y-m-d H:i:s"),
            ], [
                'uid' => $_REQUEST['id'],
            ]
        );

        $email_subject = 'Your badge application has been revoked';

        Badges::applicationStatusEmail($_REQUEST['id'], 2);
    }

    $pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;

    $limit = 20; // number of rows in page
    $offset = ($pagenum - 1 ) * $limit;
    $total = $wpdb->get_var("SELECT COUNT(`uid`) FROM `" . $wpdb->prefix . "badge_applications`");
    $num_of_pages = ceil($total / $limit);


    /*
     * Get all issuer permissions
     */
    $badge_issuer = Badges::getIssuerPermissions(get_current_user_id());

    if (!empty($badge_issuer)) {
        $badge_issuer = implode(', ', $badge_issuer);
    }

    /*
     * Get all category permissions
     */
    $category_list = Badges::getCategoryPermissions(get_current_user_id());

    if (!empty($category_list)) {
        $category_list = implode(', ', $category_list);
    }

    $query = "SELECT * FROM `" . $wpdb->prefix . "badge_applications`";

    if (!empty($_REQUEST['search_filter_name'])) {
        $query .= " LEFT JOIN `" . $wpdb->prefix . "users`
                    ON `" . $wpdb->prefix . "badge_applications`.`account_id` = `" . $wpdb->prefix . "users`.`ID`";
    }

    $query .= " LEFT JOIN `" . $wpdb->prefix . "postmeta`
                    ON `" . $wpdb->prefix . "badge_applications`.`badge_id` = `" . $wpdb->prefix . "postmeta`.`post_id`";

    if (!current_user_can('manage_issuers')) {

        // get the list of categories to filter by

        $query .= " LEFT JOIN `secure_badge_permissions`
                    ON `secure_badge_applications`.`account_id` = `secure_badge_permissions`.`user_id`";

    }

    $query .= ' WHERE 1';


    $no_users = false;

    if (!empty($_REQUEST['filter_by'])) {

        if ($_REQUEST['filter_by'] == 'all') {
            $application_status = null;
        } elseif ($_REQUEST['filter_by'] == 'pending') {
            $application_status = 0;
        } elseif ($_REQUEST['filter_by'] == 'approved') {
            $application_status = 1;
        } elseif ($_REQUEST['filter_by'] == 'revoked') {
            $application_status = 2;
        } elseif ($_REQUEST['filter_by'] == 'declined') {
            $application_status = 3;
        }

        $_SESSION['application_status'] = $application_status;

        if (isset($application_status)) {
            $query .= ' AND `application_status` = ' . $application_status;
        }

    }

    if (!empty($_REQUEST['search_filter_email'])) {
        $query .= ' AND `badge_applicant_email_address` LIKE "%' . $_REQUEST['search_filter_email'] . '%"';
    }

    if (!empty($_REQUEST['search_filter_name'])) {
        // Get a comma seperated list of the users found
        $args = [
            'fields' => 'ID',
            'search' => '*' . esc_attr( $_REQUEST['search_filter_name'] ) . '*',
        ];

        $user_query = new WP_User_Query($args);

        if (empty($user_query->get_results())) {
            $no_users = true;
        }

        $search_users = implode(', ', $user_query->get_results());

        $query .= " AND `" . $wpdb->prefix . "users`.`ID` IN (" . $search_users . ")";

    }

    if (!current_user_can('manage_issuers')) {
        $query .= " AND `secure_badge_permissions`.`issuer_id` IN (" . $badge_issuer . ")";
    }

    if (!current_user_can('manage_issuers')) {
        $query .= " OR `secure_badge_permissions`.`cat_id` IN (" . $category_list . ")";
    }

    $query .= " GROUP BY `" . $wpdb->prefix . "badge_applications`.`application_id`";

    if (!empty($_REQUEST['sort_by'])) {

        if ($_REQUEST['sort_by'] == 'date_created') {
            $_SESSION['sort_by'] = 'date_created';
        } elseif ($_REQUEST['sort_by'] == 'date_modified') {
            $_SESSION['sort_by'] = 'date_modified';
        } elseif ($_REQUEST['sort_by'] == 'date_revoked') {
            $_SESSION['sort_by'] = 'date_revoked';
        }

    }

    if ($_SESSION['sort_by'] == 'date_approved') {
        $query .= " ORDER BY `" . $wpdb->prefix . "badge_applications`.`approved_on` DESC, `" . $wpdb->prefix . "badge_applications`.`created_on` DESC";
    } elseif ($_SESSION['sort_by'] == 'date_revoked') {
        $query .= " ORDER BY `" . $wpdb->prefix . "badge_applications`.`revoked_on` DESC, `" . $wpdb->prefix . "badge_applications`.`created_on` DESC";
    } else {
        $query .= " ORDER BY `" . $wpdb->prefix . "badge_applications`.`created_on` DESC";
    }

    $query .= " LIMIT " . $offset . ", " . $limit . "";

    $rows = $wpdb->get_results($query);

?>

<link type="text/css" href="<?= WP_PLUGIN_URL; ?>/sssc-badges/css/style_admin.css" rel="stylesheet" />

<div class="wrap">

    <h1>
        Badge Applications
        <a href="<?= admin_url('admin.php?page=badge_applications_create_menu'); ?>"
            class="page-title-action">
            Add New
        </a>
    </h1>

    <?php
    if (!empty($message)) {
    ?>
        <div class="updated">
            <p>
                <?= $message; ?>
            </p>
        </div>
    <?php
    }

    ?>

    <form action="<?= $_SERVER['REQUEST_URI']; ?>" method="post" id="badgeApplicationsFilter" class="cf">

        <div class="filterWrapper">

            <div class="filterSearchWrap">

                <input type="text" name="search_filter_name" placeholder="Name" value="<?= $_REQUEST['search_filter_name'] ?>" style="min-width: 300px;" />

                <input type="text" name="search_filter_email" placeholder="Email address" value="<?= $_REQUEST['search_filter_email'] ?>" style="min-width: 300px;" />

                <input type="hidden" name="action" value="search_filter" />
                <input type="submit" value="Search" class="button" />

            </div> <!-- .searchWrap -->

            <ul class="subsubsub">
                <li><a href="admin.php?page=badge_applications_list_menu&filter_by=all"<?php
                if (empty($_SESSION['application_status']) && $_SESSION['application_status'] !== 0) {
                    ?> class="current"<?php
                }
                ?>>All</a> |</li>
                <li><a href="admin.php?page=badge_applications_list_menu&filter_by=pending"<?php
                if ($_SESSION['application_status'] == '0') {
                    ?> class="current"<?php
                }
                ?>>Pending</a> | </li>
                <li><a href="admin.php?page=badge_applications_list_menu&filter_by=approved"<?php
                if ($_SESSION['application_status'] == '1') {
                    ?> class="current"<?php
                }
                ?>>Approved</a> |</li>
                <li><a href="admin.php?page=badge_applications_list_menu&filter_by=revoked"<?php
                if ($_SESSION['application_status'] == '2') {
                    ?> class="current"<?php
                }
                ?>>Revoked</a> |</li>
                <li><a href="admin.php?page=badge_applications_list_menu&filter_by=declined"<?php
                if ($_SESSION['application_status'] == '3') {
                    ?> class="current"<?php
                }
                ?>>Declined</a></li>
            </ul>

        </div> <!-- .filterWrapper -->

    </form>

    <?php
    if (!empty($rows)) {
    ?>

    <form action="<?= $_SERVER['REQUEST_URI']; ?>" method="post" id="badgeApplications">

        <div class="bulkOptionsWrapper">

            <select name="bulk_options">
                <option value="approve">Approve Applications</option>
                <option value="revoke">Revoke Applications</option>
            </select>

            <input type="submit" value="Submit" class="button" />

        </div> <!-- .bulkOptionsWrapper -->

        <table class='wp-list-table widefat fixed striped pages'>
            <tr>
                <th scope="row" class="check-column">
                </th>
                <th>Badge applied for</th>
                <th>Badge Applicant</th>
                <th>
                    <a href="admin.php?page=badge_applications_list_menu&sort_by=date_created">
                        Date Created<?php
                            if ($_SESSION['sort_by'] == 'date_created') {
                                ?>
                                    <span class="dashicons dashicons-arrow-down"></span>
                                <?php
                            }
                        ?>
                    </a>
                </th>
                <th>
                    <a href="admin.php?page=badge_applications_list_menu&&sort_by=date_modified">
                        Date Modified<?php
                            if ($_SESSION['sort_by'] == 'date_modified') {
                                ?>
                                    <span class="dashicons dashicons-arrow-down"></span>
                                <?php
                            }
                        ?>
                    </a>
                </th>
                <th>
                    <a href="admin.php?page=badge_applications_list_menu&&sort_by=date_revoked">
                        Date Revoked<?php
                            if ($_SESSION['sort_by'] == 'date_revoked') {
                                ?>
                                    <span class="dashicons dashicons-arrow-down"></span>
                                <?php
                            }
                        ?>
                    </a>
                </th>
                <th>Status</th>
            </tr>
            <?php
            foreach ($rows as $row ) {
                $user_data = get_userdata($row->account_id);
                $badge_data = get_post($row->badge_id);
            ?>
            <tr>
                <th scope="row" class="check-column">
                    <input type="checkbox" name="bulk_actions[]" id="bulk_actions" value="<?= $row->uid ?>">
                </th>
                <td>
                    <a href="<?= admin_url('admin.php?page=badge_applications_update_menu&id=' . $row->uid) ?>">
                        <?= $badge_data->post_title ?>
                    </a>
                </td>
                <td>
                    <?= $user_data->data->display_name ?><br />
                    (<?= $row->badge_applicant_email_address ?>)
                </td>
                <td><?= date('d/m/y h:i A', strtotime($row->created_on)); ?></td>
                <td><?php
                    if ($row->application_status == 1) {
                        echo date('d/m/y h:i A', strtotime($row->approved_on));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td>
                    <?php
                    if ($row->application_status == 2) {
                        echo date('d/m/y h:i A', strtotime($row->revoked_on));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td>

                    <?php
                    if ($row->application_status == 0) {
                    ?>
                    <div>Pending</div>
                    <a href="<?= admin_url('admin.php?page=badge_applications_list_menu&id=' . $row->uid . '&approve=true') ?>" onclick="return confirm('Please confirm the application was successful.')">Approve this application</a>
                    <?php
                    } elseif ($row->application_status == 1) {
                    ?>
                    <div>Awarded</div>
                    <a href="<?= admin_url('admin.php?page=badge_applications_list_menu&id=' . $row->uid . '&revoke=true') ?>" onclick="return confirm('Please confirm you are revoking the badge approval.')">Revoke this application</a>
                    <?php
                    } elseif ($row->application_status == 2) {
                    ?>
                    <div>Revoked</div>
                    <a href="<?= admin_url('admin.php?page=badge_applications_list_menu&id=' . $row->uid . '&approve=true') ?>" onclick="return confirm('Please confirm the application was successful.')">Approve this application</a>
                    <?php
                    } elseif ($row->application_status == 3) {
                    ?>
                    <div>Declined</div>
                    <a href="<?= admin_url('admin.php?page=badge_applications_update_menu&id=' . $row->uid . '&approve=true') ?>">Update this application</a>
                    <?php
                    }
                    ?>
                </td>
            </tr>
            <?php
            }
            ?>
        </table>

        <?php

        $page_links = paginate_links([
            'base' => add_query_arg( 'pagenum', '%#%' ),
            'format' => '',
            'prev_text' => __( '&laquo;', 'text-domain' ),
            'next_text' => __( '&raquo;', 'text-domain' ),
            'total' => $num_of_pages,
            'current' => $pagenum
        ]);

        if ($page_links) {
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
        }

        ?>

    </form>
        <?php
        } elseif ($no_users === true) {
        ?>
        <div class="adminContent">
            <div class="alert">No Users found</div>
        </div>
        <?php
        } else {
        ?>
        <div class="adminContent">
            <div class="alert">No Badge Applications found</div>
        </div>
        <?php
        }
        ?>
</div>
<?php
}
