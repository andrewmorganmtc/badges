<?php
function badge_issuers_list() {

    global $wpdb;

    $pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;

    $limit = 100; // number of rows in page
    $offset = ( $pagenum - 1 ) * $limit;
    $total = $wpdb->get_var("SELECT COUNT(`id`) FROM `" . $wpdb->prefix . "badge_issuers`");
    $num_of_pages = ceil( $total / $limit );

    $query = "SELECT * FROM `" . $wpdb->prefix . "badge_issuers`";

    $query .= ' WHERE 1';

    $query .= " LIMIT " . $offset . ", " . $limit . "";

    $rows = $wpdb->get_results($query);

?>
<link type="text/css" href="<?= WP_PLUGIN_URL; ?>/sssc-badges/css/style_admin.css" rel="stylesheet" />

<div class="wrap">

    <h1>
        Badge Issuers
        <?php
        /*
        <a href="<?= admin_url('admin.php?page=badge_issuers_create'); ?>"
            class="page-title-action">
            Add New
        </a>
         *
         */
         ?>
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
    <form action="<?= $_SERVER['REQUEST_URI']; ?>" method="post" id="badgeIssuers">

        <?php
        if (!empty($rows)) {
        ?>
            <table class='wp-list-table widefat fixed striped pages'>
                <tr>
                    <th>Badge Issuer Name</th>
                    <th>Badge Issuer URL</th>
                </tr>
                <?php
                foreach ($rows as $row ) {
                ?>
                <tr>
                    <td><?= $row->name ?></td>
                    <td><?= $row->url ?></td>
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

        } else {
            ?>
            <div class="alert">No Badge Issuers found</div>
            <?php
        }
        ?>

    </form>
</div>
<?php
}