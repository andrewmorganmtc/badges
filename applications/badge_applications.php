<?php

// Badge Applications Menu

function badge_applications_menu() {

	// This is the main item for the menu
	add_menu_page(
    	'Badge Applications', // page title
    	'Applications', // menu title
    	'manage_badges', // capabilities
    	'badge_applications_list_menu', // menu slug
    	'badge_applications_list' // function
	);

	//this is a submenu
	add_submenu_page(
    	'badge_applications_list_menu', // parent slug
    	'Create New Application', // page title
    	'Create New', // menu title
    	'manage_badges', // capability
    	'badge_applications_create_menu', // menu slug
    	'badge_applications_create'
    ); // function

    //this is a submenu
    add_submenu_page(
        'badge_applications_update_menu', // hide from menu
        'Update Badge', // page title
        'Update Badge', // menu title
        'manage_badges', // capability
        'badge_applications_update_menu', // menu slug
        'badge_applications_update'
    ); // function

    //this is a submenu
    add_submenu_page(
        'badge_applications_list_menu', // parent slug
        'Export Applications', // page title
        'Export', // menu title
        'manage_badges', // capability
        'badge_applications_export_menu', // menu slug
        'badge_applications_export'
    ); // function
}

add_action('admin_menu', 'badge_applications_menu');

require_once SSSC_BADGES_ROOTDIR . 'applications/applications-list.php';
require_once SSSC_BADGES_ROOTDIR . 'applications/applications-create.php';
require_once SSSC_BADGES_ROOTDIR . 'applications/applications-update.php';
require_once SSSC_BADGES_ROOTDIR . 'applications/applications-export.php';
