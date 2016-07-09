<?php
function badge_issuers_create () {

if(!empty ($_REQUEST['insert']) && $_REQUEST['insert'] == 'Save') {
	global $wpdb;

    if (is_numeric($_REQUEST['badge_id']) && !empty($_REQUEST['badge_id']) && is_numeric($_REQUEST['user']) && !empty($_REQUEST['user']) ) {

        $badge_details = Badges::getBadge($_REQUEST['badge_id']);

        $user_details = get_userdata($_REQUEST['user']);

    	$wpdb->insert(
            'secure_badge_applications', [
                'badge_id' => $badge_details->ID,
                'account_id' => $user_details->data->ID,
                'badge_applicant_email_address' => $user_details->data->user_email,
                'created_on' => date("Y-m-d H:i:s"),
                'salt' => uniqid(mt_rand(), true),
            ]
    	);

    	$message = "Application created";

    }
}

?>
<link type="text/css" href="<?= WP_PLUGIN_URL; ?>/sssc-badges/css/style_admin.css" rel="stylesheet" />

<div class="wrap">

    <h2>Add New Issuer</h2>

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

    <form method="post" action="<?= $_SERVER['REQUEST_URI']; ?>">

        <div class="row">
            <input type="text"
        </div>

        <input type='submit' name="insert" value='Save' class='button'>

    </form>
</div>
<?php
}
