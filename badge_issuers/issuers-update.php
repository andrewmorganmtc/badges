<?php
function badge_issuers_update () {

    //update
    if (isset($_REQUEST['update'])) {
    	$wpdb->update($wpdb->prefix . 'badge_issuers', $form_data, [
    	   'id' => $id, // Where ID equals
    	]);
    }
?>

<div class="wrap">

    <h1>Update Application</h1>
    <br />
    <form method="post" action="<?= $_SERVER['REQUEST_URI']; ?>">

        <div class="row" style="margin-bottom: 15px;">
            <label for="user_id" style="display: block;font-weight: bold; padding: 10px 0; font-size: 18px;">User</label>
            <select name="user_id" class="selectBox" style="width: 50%; padding: 0 15px;">
                <option value="1">Organisation</option>
            </select>
        </div>

        <div class="row" style="margin-bottom: 15px;">
            <label for="user_id" style="display: block;font-weight: bold; padding: 10px 0; font-size: 18px;">Badge</label>
            <select name="user_id" class="selectBox" style="width: 50%; padding: 0 15px;">
                <option value="1">User Name</option>
            </select>
        </div>

        <div class="row" style="margin-bottom: 15px;">
            <label for="user_id" style="display: block;font-weight: bold; padding: 10px 0; font-size: 18px;">Email Address</label>
                <input type="text"
                    value="<?= $row->badge_applicant_email_address ?>"
                    id="email_address"
                    spellcheck="true"
                    autocomplete="off"
                    style="width: 100%; padding: 10px 15px;" />
        </div>

        <br />
        <input type="submit" name="update" value="Save" class="button">

    </form>
</div>
<?php
}

