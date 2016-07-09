<?php

function badge_applications_export() {

if (!empty($_REQUEST['badge_id']) && is_numeric($_REQUEST['badge_id'])) {

    $download_me = WP_PLUGIN_URL . '/sssc-badges/applications/export_csv.php?badge_id=' . $_REQUEST['badge_id'];

    ?>
    <script src="https://code.jquery.com/jquery-1.8.3.min.js"></script>
    <script>

    $(document).ready(function () {
        if ($('.js_downloadMePlease').length) {
            window.location = $('.js_downloadMePlease').attr('data-filename');
        }
    }); // document ready

    </script>
    <?php
    }
?>

<link type="text/css" href="<?= WP_PLUGIN_URL; ?>/sssc-badges/css/style_admin.css" rel="stylesheet" />

<div class="wrap">

<?php
    // Used for triggering a download via JS
    if (!empty($download_me)) {
        ?>
            <div class="js_downloadMePlease"  data-filename="<?= $download_me ?>" />
        <?php
    }
?>

    <h2>Export Badge Applications</h2>

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
            <label for="badgeID">Download applications for</label>

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

        <input type='submit' name="export_applications" value='Export' class='button'>

    </form>
</div>
<?php
}
