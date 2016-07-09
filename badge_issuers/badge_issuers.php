<?php

// Badge issuers Menu

function badge_issuers_menu() {

    //this is the main item for the menu
    add_menu_page('Badge Issuers', //page title
    'Badge Issuers', //menu title
    'manage_issuers', //capabilities
    'badge_issuers_list', //menu slug
    'badge_issuers_list' //function
    );

    //this is a submenu
/*
    add_submenu_page('badge_issuers_list', //parent slug
    'Create New Issuer', //page title
    'Create New', //menu title
    'manage_issuers', //capability
    'badge_issuers_create', //menu slug
    'badge_issuers_create'); //function*/


    //this submenu is HIDDEN, however, we need to add it anyways
    add_submenu_page(null, //parent slug
    'Update Issuer', //page title
    'Update', //menu title
    'manage_issuers', //capability
    'badge_issuers_update', //menu slug
    'badge_issuers_update'); //function
}

add_action('admin_menu', 'badge_issuers_menu');

require_once SSSC_BADGES_ROOTDIR . 'badge_issuers/issuers-list.php';
require_once SSSC_BADGES_ROOTDIR . 'badge_issuers/issuers-create.php';
require_once SSSC_BADGES_ROOTDIR . 'badge_issuers/issuers-update.php';
