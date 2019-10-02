<?php

/*
  Plugin Name: Bugerator
  Plugin URI: http://www.tickerator.org/bugerator
  Description: A bug tracking / issue tracking plugin
  Version: 1.0.0
  Author: David Whipple
  Author URI: http://www.tickerator.org
  License: GPL2
 */

/*  Copyright 2012  David Whipple (email : david@tickerator.org)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


/* * ************************************************************
 * Globals and other fun set up stuff to be run every time
 */
// Define our table globally so I won't have redundant code
global $wpdb;
global $bugerator_issue_table;
global $bugerator_project_table;
global $bugerator_notes_table;
global $bugerator_subscriptions;
// This table will have our issue posts. Users will be taken from the WP user tables
// and their access will be from a WP option
$bugerator_issue_table = $wpdb->prefix . "bugerator_issues";
// This table will have the project names and some meta data such as current version,
// version goal dates, etc.
$bugerator_project_table = $wpdb->prefix . "bugerator_projects";
// This table will have notes and updates to the issues above. 1 issue = many notes
$bugerator_notes_table = $wpdb->prefix . "bugerator_notes";
// This table will have user ids and their associated subscriptions
$bugerator_subscriptions = $wpdb->prefix . "bugerator_subscriptions";


// version info
global $bugerator_db_version;
$bugerator_version = "1.0.1";

// add menu option - this is in the admin menu
add_action('admin_menu', array('BugeratorMenu', 'show_menu'));


// Testing mode.  If true then a deactivate call will uninstall the tables
global $testing_mode;
$testing_mode = false;

// make file path available for inclusions.  These are php include files
global $path;
$path = (plugin_dir_path(__FILE__));

// set up upload directory
global $upload_dir;
$dir = wp_upload_dir();
$upload_dir = $dir['basedir'] . "/bugerator";



/* * ******************************************
 * Hooks and ways this gets run.
 * ****************************************** */
// Install Me please
register_activation_hook(__FILE__, array('BugeratorInstall', 'install'));
// Deactivate plugin
register_deactivation_hook(__FILE__, array('BugeratorInstall', 'deactivate'));
// Uninstall plugin - delete tables and all data
register_uninstall_hook(__FILE__, array('BugeratorInstall', 'uninstall'));

// Check version for possible upgrade
add_action('plugins_loaded', array('BugeratorInstall', 'bugerator_update_check'));

// start the process of loading the css / javascript the nice way.
add_action('wp_enqueue_scripts', 'bugerator_enqueue_scripts');

// Shortcode to run main program
add_shortcode('bugerator', 'bugerator_function');

// scripts to enqueue since it is required to do it this way to play nice with others.
function bugerator_enqueue_scripts() {
    // get the style going
    wp_register_style('bugerator', plugin_dir_url(__FILE__) . "bugerator.css");
}

/* * ***********************************************
 * Ajax section
 * ********************************************** */
add_action('wp_ajax_bugerator_project_name', array('Ajax', 'ajax_project_name'));
add_action('wp_ajax_bugerator_display_name', array('Ajax', 'ajax_suggest_name'));
add_action('wp_ajax_bugerator_get_project_list', array('Ajax', 'get_project_list'));
add_action('wp_ajax_bugerator_get_attachment', array('Ajax', 'get_attachment'));
add_action('wp_ajax_bugerator_edit_project_devs', array('Ajax', 'get_admin_dev_form'));
add_action('wp_ajax_bugerator_edit_project_admins', array('Ajax', 'get_admin_dev_form'));
add_action('wp_ajax_bugerator_delete_project_devs', array('Ajax', 'get_admin_dev_form'));
add_action('wp_ajax_bugerator_delete_project_admins', array('Ajax', 'get_admin_dev_form'));
add_action('wp_ajax_bugerator_css_value', array('Ajax', 'css_get_values'));
add_action('wp_ajax_bugerator_add_css_row', array('Ajax', 'css_add_row'));
add_action('wp_ajax_bugerator_delete_css_row', array('Ajax', 'css_delete_row'));
add_action('wp_ajax_bugerator_remove_global_user', array('Ajax', 'remove_global_user'));
add_action('wp_ajax_bugerator_get_name_list', array('Ajax', 'get_name_list'));
add_action('wp_ajax_bugerator_add_global_user', array('Ajax', 'add_global_user'));
add_action('wp_ajax_bugerator_email_preference', array('Ajax', 'change_email_preference'));

/**
 * This is called by the shortcode and directs all functions drawn from the shortcode
 * 
 * All of the shortcode traffic goes through here.  It formats the main page and uses a 
 * return from the functions to draw the rest of the page.  If no project name given defaults to all projects
 * anything returned from this function will be displayed
 * 
 * @global type $project_count - # of projects. This defines it and passes it on
 * @global string $bugerator_project_table - name of the project table so I don't have to type @wpdb->prefix
 * @global type $wpdb - Wordpress main
 * @global type $anonymous_post - Option defined here allow anonymous posting
 * @global type $upload_files - Option defined here to allow file uploads
 * @global type $date_format - Option defined here that determines the format of the date printed
 * @global type $long_date_format - same
 * @global type $user - The user that is running the script
 * @global type $project_id - Which project we are running
 * @param type $atts - attributes from the bugerator short code
 * @return string - this is the drawn page
 */
function bugerator_function($atts) {
    extract(shortcode_atts(array(
		'project' => 'ALL'
		    ), $atts));

    // show choices in the tab menu?
    $choice_menu = true;

    date_default_timezone_set('America/Denver');
    global $project_id;
    // if there is a shortcode in the project then take away the option to change it.
    if ($project <> "ALL")
	$choice_menu = false;


    // right now project is either "ALL" or whatever is set in the short code
    // override project (only if it is set to all)
    if ($project == "ALL" and isset($_GET['project'])) {
	$project = intval($_GET['project']);
    } elseif ($project == "ALL") {
	$project = -1;
    }

    // Get the number of projects so we know if we need to choose
    global $project_count;
    global $bugerator_project_table;
    global $wpdb;
    $sql = "SELECT COUNT(*) FROM " . $bugerator_project_table . " WHERE hidden = 0";
    $project_count = $wpdb->get_var($wpdb->prepare($sql));
    if ($project_count <= 1)
	$choice_menu = false;
    // if there is only 1 then choose it
    if ($project_count == 1)
	$project = $wpdb->get_var("SELECT id FROM $bugerator_project_table WHERE hidden = 0");
    $project_id = $project;

    $main = new BugeratorMain;

    // Go through the options settings and assign them.
    global $anonymous_post;
    global $upload_files;
    global $date_format;
    global $long_date_format;
    global $filesize;
    global $bugerator_subscriptions;
    $options = $main->get_options();
    if ($options['anonymous_post'] == "true")
	$anonymous_post = true;
    else
	$anonymous_post = false;
    if ($options['upload_files'] == "true")
	$upload_files = true;
    else
	$upload_files = false;
    $date_format = $options['date_format'];
    $long_date_format = $options['long_date_format'];
    $filesize = $options['filesize'];

    global $user;
    $user = wp_get_current_user();


    // only run if we are logged in
    if ($user->ID > 0) {
	// indicate they have visited for the subscription page.
	$sql = "SELECT visited FROM $bugerator_subscriptions WHERE user = '$user->ID'";
	$visited = $wpdb->get_var($sql);
	// only care if they are subscribed
	if (isset($visited)) {
	    if (0 == $visited) { // email sent since their last visit
		$sql = "UPDATE $bugerator_subscriptions SET visited = '1' WHERE user='$user->ID'";
		$wpdb->query($sql);
	    }
	}
    }

    if (isset($_GET['bugerator_nav'])) {
	$navigation = sanitize_text_field($_GET['bugerator_nav']);
    } else {
	$navigation = "choose";
    }


    if (isset($_GET['issue']))
	$issue_id = intval($_GET['issue']);
    else
	$issue_id = 0;

    // If you edit bug right after adding it runs the report tab so reroute it to the show detail tab
    if (isset($_POST['bugerator_issue_edit']) or isset($_POST['bugerator_admin_issue_edit'])
	    or isset($_POST['bugerator_comment_add'])) {
	$_GET['issue'] = intval($_POST['issue_id']);
	$navigation = "display";
    }



    // $menu is an array of links including the active link
    $menu = $main->get_menu($navigation, $choice_menu, $project, $issue_id);

    switch ($navigation) {
	case "choose":
	    if ($choice_menu)
		$output = $main->choose_project();
	    else
		$output = $main->list_issues($project);
	    break;
	case "list":
	    $output = $main->list_issues($project);
	    break;
	case "admin":
	    $output = $main->admin();
	    break;
	case "add":
	    $output = $main->add_bug($project);
	    break;
	case "display":
	    $output = $main->display_bug();
	    break;
	case "map":
	    $output = $main->display_map($project);
	    break;
	case "comment":
	    $output = $main->edit_comment();
	    break;
	case "profile":
	    $output = $main->profile();
	    break;
	default:
	    if ("ALL" == $project) {
		$output = $main->choose_project();
	    } else {
		$output = $main->list_issues($project);
	    }
    }



    $output = "<div class=bugerator_page id=bugerator_page style='margin: 0px " .
	    $options['margin'] . "px'>\r\n" .
	    $menu . "\r\n<div class=bugerator_content id=bugerator_content >\r\n" .
	    $output . "\r\n</div><!-- bugerator_content -->\r\n</div><!-- bugerator_page -->";
    return $output;
}

/* Ajax class
 * group all of these together.
 */

/**
 * Class that handles all ajax calls
 * 
 * All ajax calls are run through this class.  It is called via add_action from 
 * Wordpress
 * @version Release: 1.0
 * @since   1.0
 */
class Ajax {

    /**
     * Checks if a project name exists
     * 
     * When creating a new project the name must be unique. This ajax call returns an 
     * error message or nothing.
     * 
     * @global string $bugerator_project_table - project table name
     * @global type $wpdb
     */
    function ajax_project_name() {
	check_ajax_referer('bugerator', 'security');
	$project_name = $_POST['project_name'];
	global $bugerator_project_table;
	global $wpdb;
	$sql = "SELECT COUNT(*) FROM " . $bugerator_project_table . " WHERE name = '$project_name'";
	$project_count = $wpdb->get_var($wpdb->prepare($sql));
	if ($project_count > 0)
	    echo "Project name already taken.";
	else
	    echo "";
	die();
    }

    /**
     * This suggests a user name for the owner of a project
     * 
     * Queries all of the user names and will suggest a user name based on what is being typed.
     * 
     * @global type $wpdb
     */
    function ajax_suggest_name() {
	check_ajax_referer('bugerator', 'security');

	global $wpdb;
	$display_name = $wpdb->prepare($_POST['display_name']);
	$sql = "SELECT display_name FROM $wpdb->users WHERE display_name LIKE ('" . $display_name . "%') " .
		"ORDER BY display_name limit 6";
	$result = $wpdb->get_results($sql);

	$output = "";
	// build the output in such a way so that it triggers the javascript fill in form
	foreach ($result as $name) {
	    $output .= "<a onclick=\"fill_in_name('$name->display_name')\" >" .
		    $name->display_name . "</a><br/>";
	    // if we have an exact match we don't need the search
	    if ($name->display_name == $display_name) {
		die();
	    }
	}
	echo $output;

	die();
    }

    /**
     * Returns a file attachment asked for
     * 
     * For security file attachments are not accessable directly.  This fetches the file and 
     * then returns it as part of the page. - image or text
     * 
     * @global type $wpdb
     * @global string $upload_dir - where file uploads are
     * @global string $bugerator_issue_table
     * @global string $bugerator_notes_table
     */
    function get_attachment() {
	check_ajax_referer("bugerator_get_attachment", "security");
	global $wpdb;
	global $upload_dir;
	global $bugerator_issue_table;
	global $bugerator_notes_table;

	// request type - issue or comment
	$request_type = $_GET['post'];
	$request_id = $_GET['id'];
	if ("issue" == $request_type) {
	    $sql = "SELECT filename FROM $bugerator_issue_table ";
	} elseif ("notes" == $request_type) {
	    $sql = "SELECT filename FROM $bugerator_notes_table ";
	} else {
	    header("HTTP/1.0 404 Not Found");
	    require TEMPLATEPATH . '/404.php';
	    die();
	}
	$sql .= "WHERE id = '$request_id'";
	$file = $upload_dir . "/" . $wpdb->get_var($sql);

	if (file_exists($file)) {
	    if ("txt" == strtolower(substr($file, -3)) or
		    "log" == strtolower(substr($file, -3))) {
		$fp = fopen($file, 'r');
		$file_output = fread($fp, filesize($file));
		fclose($fp);
		$output = "<textarea cols='60' rows='6' >" . htmlspecialchars($file_output) .
			"</textarea>";
		echo $output;
	    } else {
		header("Content-type: image/png");
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		ob_clean();
		flush();
		readfile($file);
	    }
	} else {
	    header("HTTP/1.0 404 Not Found");
	    require TEMPLATEPATH . '/404.php';
	}

	die();
    }

    /**
     * Returns the list of projects for the admin menu
     * 
     * I didn't want to push all of the project info through the page all the time so 
     * this is an ajax call that returns formatted project information.
     * 
     * @global type $wpdb
     * @global type $path
     * @global string $bugerator_project_table
     */
    function get_project_list() {
	// from the admin menu
	check_ajax_referer('bugerator', 'security');

	global $wpdb;
	global $path;
	global $bugerator_project_table;

	// the bugerator_projects_tpl.php file has a generic table
	ob_start();
	include_once($path . "/bugerator_admin_view_projects_tpl.php");
	$issue_table = ob_get_clean();
	// get option values
	$statuses = explode(",", get_option('bugerator_project_statuses'));

	// Going to do a search and replace
	$search_array = array(
	    "_PROJECT_NAME_ ",
	    "_ID_",
	    "_NAME_",
	    "_CURRENT_VERSION_",
	    "_VERSION_DATE_",
	    "_STATUS_",
	    "_NEXT_VERSION_",
	    "_RELEASE_",
	    "_ADMINS_",
	    "_DEVELOPERS_",
	    "_EDIT_LINK_"
	);
	$sql = "SELECT ID,display_name from " . $wpdb->users;
	$name_array = $wpdb->get_results($sql);
	foreach ($name_array as $name) {
	    $display_names[$name->ID] = $name->display_name;
	}
	//href='$post->guid . "&bugerator_nav=$tab$project$issue' >
	$sql = "SELECT * FROM $bugerator_project_table WHERE hidden = 0";
	$sql = $wpdb->prepare($sql);
	$results = $wpdb->get_results($sql);

	$options = BugeratorMain::get_options();
	$date_format = $options['date_format'];

	$output = "";
	foreach ($results as $result) {
	    $thisuser = new WP_User($result->owner);
	    $versions = explode(",", $result->version_list);
	    $goals = explode(",", $result->version_goal_list);
	    $next_version_index = $result->current_version + 1;
	    if (isset($versions[$next_version_index]))
		$next_version = $versions[$next_version_index];
	    else
		$next_version = "";
	    $current_version = $versions[$result->current_version];
	    if (isset($goals[$next_version_index]))
		$next_date = date($date_format, strtotime($goals[$next_version_index]));
	    else
		$next_date = "";
	    // get admin list
	    $admins = explode(",", $result->admins);
	    $developers = explode(",", $result->developers);
	    $admin_names = "";
	    $developer_names = "";

	    if ($admins[0] <> "") {
		foreach ($admins as $key)
		    $admin_names .= $display_names[$key] . " <br/>\r\n";
	    }
	    // now developer list
	    if ($developers[0] <> "") {
		foreach ($developers as $key)
		    $developer_names .= $display_names[$key] . "<br/>\r\n";
	    }

	    $edit_link = $_POST['post_id'] . "&bugerator_edit_project=$result->id&bugerator_nav=admin&project=1" .
		    "&active_tab=projects";

	    $replace_array = array(
		$result->name,
		$result->id,
		$thisuser->display_name,
		$current_version,
		date($date_format, strtotime($result->version_date)),
		$statuses[$result->status],
		$next_version,
		$next_date,
		$admin_names,
		$developer_names,
		$edit_link
	    );
	    $output .= str_replace($search_array, $replace_array, $issue_table);
	}

	echo $output;
	die();
    }

    /**
     * Form to edit the developers of a project
     * 
     * This is for editing the developers in a project
     * will return a form of existing developers with the ability to delete
     * and a drop down box with the ability to add a dev
     * @global type $wpdb
     * @global string $bugerator_project_table
     * @global type $path
     */
    function get_admin_dev_form() {
	check_ajax_referer('bugerator', 'security');
	global $wpdb;
	global $bugerator_project_table;
	global $path;
	$success_msg = "";
	$error_msg = "";

	// could be requesting admins or developers
	if ("bugerator_edit_project_admins" == $_POST['action'] or
		"bugerator_delete_project_admins" == $_POST['action'])
	    $field = "admins";
	else
	    $field = "developers";

	if (isset($_POST['selection'])) {
	    $messages = Ajax::ajax_process_new_admin($field);
	    if (is_array($messages)) {
		$success_msg = $messages[0];
		$error_msg = $messages[1];
	    }
	}

	if (isset($_POST['deleteme'])) {
	    $messages = Ajax::ajax_delete_admin($field);
	    if (is_array($messages)) {
		$success_msg = $messages[0];
		$error_msg = $messages[1];
	    }
	}

	$id = intval($_POST['id']);
	if (0 == $id)
	    die("Invalid project.");
	// get the admins/dev list
	$sql = "SELECT $field FROM $bugerator_project_table WHERE id = '$id'";
	$edit_list = explode(",", $wpdb->get_var($wpdb->prepare($sql)));

	//get the whole user list
	$sql = "SELECT ID,display_name FROM $wpdb->users ORDER BY display_name";
	$users = $wpdb->get_results($sql);
	foreach ($users as $user) {
	    $userlist[$user->ID] = $user->display_name;
	}
	// if no developers/admins make it an empty array so the foreach in the template doesn't run
	if ($edit_list[0] == "")
	    $edit_list = array();
	$user = wp_get_current_user();

	include_once($path . "/bugerator_admin_edit_project_admins_tpl.php");

	die();
    }

    /**
     * If a new admin is added this function adds the new admin to a project
     * 
     *  quickly add a new admin
     * then return. the other query will pick up the change in realtine
     * @global type $wpdb
     * @global string $bugerator_project_table
     * @param type $field
     * @return type
     */
    function ajax_process_new_admin($field) {
	check_ajax_referer('bugerator', 'security');
	global $wpdb;
	global $bugerator_project_table;

	$id = intval($_POST['id']);
	$selection = intval($_POST['selection']);
	if (0 == $selection)
	    return array('', 'Choose an admin.');
	$sql = "SELECT $field FROM $bugerator_project_table WHERE id = '$id'";
	$result = $wpdb->get_var($wpdb->prepare($sql));

	if ("" == $result or NULL == $result)
	    $row['developers'] = $selection;
	else {
	    $thisrow = explode(",", $result);
	    if (false == array_search($selection, $thisrow))
		$thisrow[] = $selection;
	    $row['developers'] = implode(",", $thisrow);
	}
	$wpdb->show_errors();
	$sql = "UPDATE $bugerator_project_table SET $field = '" . $row['developers'] . "' WHERE id = '$id'";
	if (false == $wpdb->query($sql))
	    return array('', "Cannot complete update");
	return array("Update Successfull", "");
    }

    /**
     * Delete an admin off a project
     * 
     * Projects have individual lists. This deletes an admin off the list.
     * 
     * @global type $wpdb
     * @global string $bugerator_project_table
     * @param type $field
     * @return type
     */
    function ajax_delete_admin($field) {
	check_ajax_referer('bugerator', 'security');
	global $wpdb;

	global $bugerator_project_table;
	$deleteme = intval($_POST['deleteme']);
	$project = intval($_POST['project']);
	$sql = "SELECT $field from $bugerator_project_table WHERE id = '$project'";
	$list = explode(",", $wpdb->get_var($wpdb->prepare($sql)));
	foreach ($list as $list) {
	    if ($list != $deleteme)
		$newlist[] = $list;
	}
	if (!isset($newlist))
	    $output = "";
	else
	    $output = implode(",", $newlist);
	$sql = "UPDATE $bugerator_project_table SET $field = '$output' where id = '$project'";
	if (false === $wpdb->query($wpdb->prepare($sql)))
	    return array('', "Unable to delete" . $wpdb->print_error());
	return array(substr($field, 0, -1) . " deleted", '');
    }

    /**
     * Returns some posible css values for a aoption
     * 
     * If a css key such as background-color is given this returns a summary of options
     * or what to add to the value field.
     */
    function css_get_values() {
	check_ajax_referer('bugerator_options', 'security');
	if (!isset($_POST['css_property']))
	    die('Invalid input.');
	$property = $_POST['css_property'];
	$key = $_POST['css_key'];
	switch ($property) {
	    case "background-color":
		echo "Enter color code like #FF0000
                    or color name like black";
		break;
	    case "border":
		echo "
<select id='css_value_$key' onChange=(easy_value_css('$key')) >
    <option value =' '>Choose:
    <option value='1px solid'>1px solid
    <option value='1px dotted'>1px dotted
    <option value='2px solid'>2px solid
    <option value='2px dotted'>2px dotted
</select>";
		break;
	    case "color":
		echo "Enter color code like #FF0000
                    or color name like black";
		break;
	    case "font-size":
		echo "
<select id='css_value_$key' onChange=(easy_value_css('$key')) >
    <option value =' '>Choose:
    <option value='.8em'>.8em
    <option value='1em'>1em
    <option value='1.2em'>1.2em
    <option value='1.4em'>1.4em
    <option value='1.6em'>1.6em
    <option value='1.8em'>1.8em
    <option value='2.0em'>2.0em
</select>";
		break;
	    case "font-weight":
		echo "
<select id='css_value_$key' onChange=(easy_value_css('$key')) >
    <option value =' '>Choose:
    <option value='normal'>normal
    <option value='bold'>bold
    <option value='bolder'>bolder
    <option value='lighter'>lighter
</select>";
		break;
	    case "margin":
		echo "
<select id='css_value_$key' onChange=(easy_value_css('$key')) >
    <option value =' '>Left Top Right Bottom
    <option value='5px 5px 5px 5px'>5px 5px 5px 5px
    <option value='10px 10px 10px 10px'>10px 10px 10px 10px
    <option value='15px 15px 15px 15px'>15px 15px 15px 15px
</select>";
		break;
	    case "padding":
		echo "
<select id='css_value_$key' onChange=(easy_value_css('$key')) >
    <option value =' '>Left Top Right Bottom
    <option value='5px 5px 5px 5px'>5px 5px 5px 5px
    <option value='10px 10px 10px 10px'>10px 10px 10px 10px
    <option value='15px 15px 15px 15px'>15px 15px 15px 15px
</select>";
		break;
	    case "text-decoration":
		echo "
<select id='css_value_$key' onChange=(easy_value_css('$key')) >
    <option value =' '>Choose:
    <option value='none'>none
    <option value='underline'>underline
    <option value='overline'>overline
    <option value='line-through'>line-through
</select>";
		break;
	    default:
		die();
	}
	die();
    }

    /**
     * Takes in a new css option and adds it to the file
     * 
     * This takes in a page name and a css option and formats the css and 
     * updates the bugerator.css file
     * 
     * @global stdClass $post
     */
    function css_add_row() {
	check_ajax_referer('bugerator_options', 'security');
	global $post;
	$post = new stdClass();
	$post->guid = "";
	$wp_option = $_POST['wp_option'];
	$all_css = BugeratorMenu::bugerator_get_css();
	$css = $all_css[$wp_option];
	$css_key = $_POST['css_key'];
	$css_array = BugeratorMenu::css_parse($css);
	if (!isset($css_array[$css_key])) {
	    echo "Invalid option.";
	    echo BugeratorMenu::get_css_change_form($css_array, $wp_option);
	    die();
	}
	// add the new property to the array
	$css_property = $_POST['css_property'];
	$css_value = $_POST['css_value'];
	$css_array[$css_key][$css_property] = $css_value;

	// turn the array into a string
	$css_string = BugeratorMenu::css_unparse($css_array);
	$all_css[$wp_option] = $css_string;
	BugeratorMenu::bugerator_put_css($all_css);
	echo '<a href="">
            <input type="button" class="button-primary" value="Refresh page" >
        </a>';

	echo BugeratorMenu::get_css_change_form($css_array, $wp_option);
	echo '<a href="">
            <input type="button" class="button-primary" value="Refresh page" >
        </a>';

	die();
    }

    /**
     * Deletes a css item
     * 
     * This deletes a row off the css file for a specific page.
     * 
     * @global stdClass $post
     */
    function css_delete_row() {
	check_ajax_referer('bugerator_options', 'security');
	global $post;
	$post = new stdClass();
	$post->guid = "";
	$all_css = BugeratorMenu::bugerator_get_css();
	$wp_option = $_POST['wp_option'];
	$css = $all_css[$wp_option];
	$css_key = $_POST['css_key'];
	$css_array = BugeratorMenu::css_parse($css);
	if (!isset($css_array[$css_key])) {
	    echo "Invalid option.";
	    echo BugeratorMenu::get_css_change_form($css_array, $wp_option);
	    die();
	}
	// Delete the property from the array
	$css_property = $_POST['css_property'];
	unset($css_array[$css_key][$css_property]);

	// turn the array into a string
	$css_string = BugeratorMenu::css_unparse($css_array);
	$all_css[$wp_option] = $css_string;
	BugeratorMenu::bugerator_put_css($all_css);
	echo '<a href="">
            <input type="button" class="button-primary" value="Refresh page" >
        </a>';

	echo BugeratorMenu::get_css_change_form($css_array, $wp_option);
	echo '<a href="">
            <input type="button" class="button-primary" value="Refresh page" >
        </a>';

	die();
    }

    /**
     * Removes an admin or developer off of the global admin/devs option
     * 
     * Removes an admin or developer off of the global admin/devs option
     * @global type $wpdb
     */
    function remove_global_user() {
	check_ajax_referer('bugerator_users', 'security');
	global $wpdb;
	$user_type = $_POST['deleteme'];
	$key = intval($_POST['id']);

	if ($user_type <> "developers" and $user_type <> "admins") {
	    echo "Invalid option.";
	    die();
	}

	$list = get_option("bugerator_$user_type");

	// get and format all users on the system.
	$sql = "SELECT ID,display_name from " . $wpdb->users;
	$name_array = $wpdb->get_results($sql);
	foreach ($name_array as $name) {
	    $display_names[$name->ID] = $name->display_name;
	}


	$list_array = explode(",", $list);

	if ("" == $list_array[0]) {// nobody in the list now
	    echo "There are no global $user_type.\r\n";
	    update_option('bugerator_$user_type', '');
	    die();
	}
	$remove_key = array_search($key, $list_array);
	unset($list_array[$remove_key]);
	if (count(0 == $list_array)) {
	    echo "There are no global $user_type.\r\n";
	    update_option("bugerator_$user_type", "");
	    die();
	}

	$user = wp_get_current_user();
	$user_id = $user->ID;

	$names = "";
	foreach ($list_array as $this_key) {
	    $names .= $display_names[$this_key];
	    if ("developers" == $user_type or $this_key <> $user_id)
		$names .= " <span id='rm_$user_type" . "_$this_key' >
		    <a onClick='remove_user(\"$user_type\",\"$this_key\")'>Click to remove " .
			substr($user_type, 0, -1) . ".</a></span> ";
	    $names .= " <br/>\r\n";
	}
	echo $names;
	$list_string = implode(",", $list_array);

	update_option("bugerator_$user_type", $list_string);
	die();
    }

    /**
     * Takes in the developer/admin option and returns a valid list of who you can add
     * 
     * Takes in the developer/admin option and returns a valid list of who you can add
     * 
     * @global type $wpdb
     */
    function get_name_list() {
	check_ajax_referer('bugerator_users', 'security');

	global $wpdb;
	$which = $_POST['type'];
	$list_array = explode(",", get_option("bugerator_$which"));

	// get and format all users on the system.
	$sql = "SELECT ID,display_name from " . $wpdb->users;
	$name_array = $wpdb->get_results($sql);
	$display_names = array();
	foreach ($name_array as $name) {
	    if (array_search($name->ID, $list_array) !== false)
		continue;
	    $display_names[$name->ID] = $name->display_name;
	}
	if (0 == count($display_names)) {
	    echo "All users are already $which.";
	    die();
	}
	echo "<select id='add_$which' onChange=(add_user(\"$which\")) >
	<option value=''> ";
	foreach ($display_names as $id => $name) {
	    echo "<option value='$id' >$name\r\n";
	}
	echo "</select>";
	die();
    }

    /**
     * Adds a user to the global developer / admin list
     * 
     * Adds a user to the global developer / admin list. They will be able to edit all projects
     * @global type $wpdb
     */
    function add_global_user() {
	check_ajax_referer('bugerator_users', 'security');
	global $wpdb;
	$type = $_POST['type'];
	$id = intval($_POST['id']);
	if ($type <> "developers" and $type <> "admins") {
	    echo "Invalid option.";
	    die();
	}

	$list_array = explode(",", get_option("bugerator_$type"));
	if ("" == $list_array[0])
	    $list_array[0] = $id;
	else {
	    if (false === array_search($id, $list_array))
		$list_array[] = $id;
	}

	$list_string = implode(",", $list_array);
	update_option("bugerator_$type", $list_string);

	// get and format all users on the system.
	$sql = "SELECT ID,display_name from " . $wpdb->users;
	$name_array = $wpdb->get_results($sql);
	foreach ($name_array as $name) {
	    $display_names[$name->ID] = $name->display_name;
	}
	$user = wp_get_current_user();
	$user_id = $user->ID;

	$names = "";

	foreach ($list_array as $this_key) {
	    $names .= $display_names[$this_key];
	    if ("developers" == $type or $this_key <> $user_id)
		$names .= " <span id='rm_$type" . "_$this_key' >
		    <a onClick='remove_user(\"$type\",\"$this_key\")'>Click to remove " .
			substr($type, 0, -1) . ".</a></span> ";
	    $names .= " <br/>\r\n";
	}
	echo $names;
	die();
    }

    function change_email_preference() {
	check_ajax_referer('bugerator_profile', 'security');
	$which_preference = $_POST['which_preference'];
	$user = wp_get_current_user();

	// preference is either one for one email or all for all emails.
	// decide if we already are all
	$email_all_subscribers = explode(",", get_option('bugerator_subscribers_all_email'));
	if (false === array_search($user->ID, $email_all_subscribers))
	    $all = false;
	else
	    $all = true;
	// if we already have what we want just say yes it worked and move in
	if (($which_preference == "all" and true == $all) or
		("one" == $which_preference and false == $all)) {
	    echo "Preference updated.";
	    die();
	}

	if ("all" == $which_preference) {
	    $email_all_subscribers[] = $user->ID;
	} elseif ("one" == $which_preference) {
	    // take us out of the array;
	    $key = array_search($user->ID, $email_all_subscribers);
	    unset($email_all_subscribers[$key]);
	} else {
	    // somebody is messing with post prefereces
	    echo "Nothing selected.";
	    die();
	}
	if (count($email_all_subscribers) > 0) {
	    if ("" == $email_all_subscribers[0])
		$email_string = $user->ID;
	    else
		$email_string = implode(",", $email_all_subscribers);
	    update_option('bugerator_subscribers_all_email', $email_string);
	} else {
	    update_option('bugerator_subscribers_all_email', '');
	}

	echo "Preference updated.";
	die();
    }

}

// end Ajax class

/** * ****************************
 * Main Class
 * Displays the different pages visible to the user
 * Queries the database and that kind of stuff
 */
class BugeratorMain {

    function __construct() {
	wp_enqueue_style('bugerator');
	$this->nonce = wp_create_nonce('bugerator');
    }

    /**
     * returns html tables of all of the issues associated with a project
     * 
     * returns html tables of all of the issues associated with a project
     * 
     * @global type $path
     * @global type $post
     * @global type $wpdb
     * @global string $bugerator_issue_table
     * @global string $bugerator_project_table
     * @global type $date_format - selectible option
     * @param type $project - which project
     * @return string - sends it to be displayed
     */
    function list_issues($project = -1) {
	if ("ALL" == $project and !isset($_GET['project'])) // this means nothing is selected.
	    return $this->choose_project();
	if (-1 == $project and $_GET['project']) {
	    $project = intval($_GET['project']);
	}
	if (-1 == $project or "-1" == $project or 0 == $project or "0" == $project)
	    return $this->choose_project();

	wp_enqueue_script('sorttable', plugins_url('sorttable.js', __FILE__));
	$project = intval($project);

	global $path;
	global $post;
	global $wpdb;
	global $bugerator_issue_table;
	global $bugerator_project_table;
	global $date_format;
	$message = "";
	$error = "";

	if (isset($_POST['bulk_edit_issue_list']) and
		wp_verify_nonce($_POST['issue_list_nonce'], 'bugerator_list')) {
	    $return_array = $this->issue_bulk_update();
	    if (!is_array($return_array))
		return $return_array;
	    $message = $return_array[0];
	    $error = $return_array[1];
	}


	// default statuses: New,Open,Assigned,Duplicate,Need Info,Resolved,Closed,Abandoned,Testing,Completed
	// get status messages and sort value
	$statuses = explode(",", get_option('bugerator_statuses'));
	$status_sort_sql = get_option('bugerator_status_sort');
	$status_sort = explode(",", $status_sort_sql);
	for ($x = 0; $x < count($status_sort); $x++)
	    $status_sort[$x] = "<p style='display: none;' >" . $status_sort[$x] . "</p>";


	// get styles from options and put it together
	$status_backgrounds = explode(",", get_option('bugerator_status_colors'));
	$status_text = explode(",", get_option('bugerator_status_text_colors'));
	for ($x = 0; $x < count($status_text); $x++) {
	    $style[$x] = "background: " . $status_backgrounds[$x] . "; color: " . $status_text[$x] . ";";
	}



	$sql = "SELECT * FROM $bugerator_issue_table WHERE project_id = '$project' AND hidden = 0
	ORDER BY FIELD(status,$status_sort_sql), priority, submitted";
	$results = $wpdb->get_results($wpdb->prepare($sql));
	if (!$results) {
	    return "There are no issues for this project.";
	}

	// get the versions
	$sql = "SELECT version_list FROM $bugerator_project_table WHERE id = '$project'";
	$version_list = explode(",", $wpdb->get_var($sql));

	$is_admin = $this->is_admin($project);

	// get fields for the bulk change form
	if ($is_admin):

	    // Get a list of all users
	    $sql = "SELECT ID, display_name FROM " . $wpdb->users . " ORDER BY display_name";
	    $user_results = $wpdb->get_results($sql, ARRAY_N);
	    $big_user_list = array();
	    foreach ($user_results as $result) {
		$big_user_list[$result[0]] = $result[1];
	    }
	    $statuses_in_use = explode(",", get_option('bugerator_statuses_inuse'));
	    $user_id = get_current_user_id();
	endif;



	$nonce = wp_create_nonce('bugerator_list');

	$output_array = array();
	foreach ($results as $result) {
	    // initialize the output
	    $row = array();
	    if ($result->assigned > 0) {
		$assigned = new wp_user($result->assigned);
		$assigned_name = $assigned->display_name;
	    } else {
		$assigned_name = "";
	    }
	    $submitter = new wp_user($result->submitter);
	    if ("" == $submitter->display_name)
		$display_name = "Anonymous";
	    else
		$display_name = $submitter->display_name;
	    $row['submitter'] = $display_name;
	    $row['status'] = $status_sort[$result->status] . $statuses[$result->status];
	    $row['style'] = $style[$result->status];
	    $row['date'] = date($date_format, strtotime($result->submitted));
	    $row['id'] = $result->id;
	    $row['title'] = stripslashes($result->title);
	    $row['assigned'] = $assigned_name;
	    $row['assigned_id'] = $result->assigned;
	    $row['priority'] = $result->priority;
	    $row['version'] = $version_list[$result->version];
	    $row['link'] = "<a class='bugerator_issue_link' href='" .
		    $post->guid . "&bugerator_nav=display&project=$project&issue=" . $row['id'] . "' ?>";
	    // hide completed rows
	    if ("10" == $result->status or
		    "9" == $result->status or
		    "8" == $result->status or
		    "7" == $result->status)
		$row['completed'] = " completed ";
	    else
		$row['completed'] = "";
	    // strike through certain issues
	    // default statuses: New,Open,Assigned,In Progress, Testing, Duplicate,Need Info,Resolved,Closed,Abandoned,Completed
	    if ("5" == $result->status or
		    "6" == $result->status or
		    "7" == $result->status or
		    "8" == $result->status or
		    "9" == $result->status or
		    "10" == $result->status)
		$row['style'] .= " text-decoration: line-through;";
	    $output_array[] = $row;
	}

	// the bugerator_layouts_tpl.php file defines $issue_table for a generic table
	ob_start();
	include_once($path . "/bugerator_issue_list_tpl.php");
	$output = ob_get_clean();

	return $output;
    }

    /**
     * Maps out the project issues groupd by version so you can see what is in the future
     * 
     * Maps out the project issues groupd by version so you can see what is in the future
     * @global type $path
     * @global type $post
     * @global type $wpdb
     * @global string $bugerator_issue_table
     * @global string $bugerator_project_table
     * @param type $project
     * @return type - the output to be displayed
     */
    function display_map($project = -1) {
	if ("ALL" == $project and !isset($_GET['project'])) // this means nothing is selected.
	    return $this->choose_project();
	if (-1 == $project and $_GET['project']) {
	    $project = intval($_GET['project']);
	}
	if (-1 == $project or -1 == $project or 0 == $project or "0" == $project)
	    return $this->choose_project();
	global $path;
	global $post;
	global $wpdb;
	global $bugerator_issue_table;
	global $bugerator_project_table;

	// get the status list and the sorting order so completed are at the bottom.
	$statuses = explode(",", get_option('bugerator_statuses'));
	$status_sort_sql = get_option('bugerator_status_sort');

	// get the name and versions
	$sql = "SELECT current_version, version_list, version_goal_list, name FROM " .
		"$bugerator_project_table WHERE id = $project";
	$project_info = $wpdb->get_row($sql);

	$version_list = explode(",", $project_info->version_list);
	$goal_list = explode(",", $project_info->version_goal_list);

	$types = explode(",", get_option('bugerator_types'));

	$sql = "SELECT id, type, title, status, priority, version " .
		"from $bugerator_issue_table WHERE project_id = '$project' ORDER BY version, " .
		"FIELD(status,$status_sort_sql), submitted";
	$results = $wpdb->get_results($sql);

	$status_backgrounds = explode(",", get_option('bugerator_status_colors'));
	$status_text = explode(",", get_option('bugerator_status_text_colors'));
	for ($x = 0; $x < count($status_text); $x++) {
	    $style[$x] = "background: " . $status_backgrounds[$x] . "; color: " . $status_text[$x] . ";";
	}



	ob_start();
	include_once($path . "bugerator_show_map_tpl.php");
	$output = ob_get_clean();
	return $output;
    }

    /**
     * takes in the check boxes the admin choose on the issue list and applies the selected changes
     * 
     * On the main issue list page you can select mutiple issues and update them all.  This does that.
     * 
     * @global type $wpdb
     * @global string $bugerator_issue_table
     * @global string $bugerator_notes_table
     * @global string $bugerator_project_table
     * @global type $user
     * @return type
     */
    function issue_bulk_update() {
	global $wpdb;
	global $bugerator_issue_table;
	global $bugerator_notes_table;
	global $bugerator_project_table;
	global $project_id;
	global $user;

	if (!$this->is_admin())
	    return array('', 'You must be an admin.');
	$selections_to_edit = $_POST['edit_list_select'];
	$selections_to_edit_sql = "( \"" . implode('","', $selections_to_edit) . "\" )";

	$sql = "SELECT id, assigned, status, priority, version, type,project_id from $bugerator_issue_table " .
		"WHERE id IN $selections_to_edit_sql";
	$results = $wpdb->get_results($sql, ARRAY_A);

	// get statuses for log
	$statuses = explode(",", get_option('bugerator_statuses'));

	if (0 == count($selections_to_edit))
	    return array("", "You need to choose some lines");

	switch ($_POST['list_action']) {
	    case " ":
		return array('', "Nothing selected.");
		break;
	    case "assign":
		goto assignment;
		break;
	    case "status":
		goto status;
		break;
	    case "priority":
		goto priority;
		break;
	    case "version":
		goto version;
		break;
	    case "delete":
		goto delete;
		break;
	    default:
		return array('', "Nothing selected.");
	}

	// we picked to update the assignments.
	assignment:
	$new_assignment = intval($_POST['new_assigned_user']);
	$new_user = get_userdata($new_assignment);
	$sql = "UPDATE $bugerator_issue_table SET assigned = '$new_assignment' ," .
		"updated = '" . date("Y-m-d H:i:s", time()) . "' WHERE id IN " . $selections_to_edit_sql;
	$x = 0;
	foreach ($selections_to_edit as $this_selection) {
	    $thisuser = get_userdata(intval($results[$x]['assigned']));

	    if (is_object($thisuser)) {
		$thisname = " from " . $thisuser->display_name;
		$namecheck = $thisuser->display_name;
	    } else {
		$thisname = "";
		$namecheck = "";
	    }
	    if (!is_object($new_user))
		$assignment = "Removed assignment.";
	    else {
		if ($namecheck <> $new_user->display_name) {
		    $assignment = "Assignement changed$thisname to $new_user->display_name.";
		} else {
		    unset($selections_to_edit[$x]);
		}
	    }
	    $x++;
	    if (isset($assignment)) {
		$sql_log[] = "INSERT INTO $bugerator_notes_table (issue_id, notes, filename, user, time, hidden ) values (" .
			"'$this_selection', '$assignment', '0'," .
			"'$user->ID', " .
			"'" . date("Y-m-d H:i:s", time()) . "','0' )";
		$log[] = $assignment;
	    }
	}

	goto query;

	status:

	$new_status = intval($_POST['new_status']);
	$sql = "UPDATE $bugerator_issue_table SET status = '$new_status' ," .
		"updated = '" . date("Y-m-d H:i:s", time()) . "' WHERE id IN " . $selections_to_edit_sql;
	$x = 0;
	foreach ($selections_to_edit as $this_selection) {
	    if ($new_status <> $results[$x]['status']) {
		$thisstatus = $statuses[$results[$x]['status']];

		$sql_log[] = "INSERT INTO $bugerator_notes_table (issue_id, notes, filename, user, time,hidden ) values (" .
			"'$this_selection', 'Updated status from $thisstatus to $statuses[$new_status].'" .
			", '0', '$user->ID', " .
			"'" . date("Y-m-d H:i:s", time()) . "','0' )";
		$log[] = "Updated status from $thisstatus to $statuses[$new_status].";
	    } else {
		unset($selections_to_edit[$x]);
	    }
	    $x++;
	}
	goto query;

	priority:
	$new_priority = intval($_POST['new_priority']);
	$sql = "UPDATE $bugerator_issue_table SET priority = '$new_priority' ," .
		"updated = '" . date("Y-m-d H:i:s", time()) . "' WHERE id IN " . $selections_to_edit_sql;
	$x = 0;
	foreach ($selections_to_edit as $this_selection) {
	    if ($new_priority <> $results[$x]['priority']) {
		$sql_log[] = "INSERT INTO $bugerator_notes_table (issue_id, notes, filename, user, time, hidden ) values ( " .
			"'$this_selection', 'Updated priority from " . $results[$x]['priority'] . " to: $new_priority " .
			".', '0', '$user->ID', " .
			"'" . date("Y-m-d H:i:s", time()) . "','0' )";
		$log[] = "Updated priority from " . $results[$x]['priority'] . " to $new_priority.";
	    } else {
		unset($selections_to_edit[$x]);
	    }
	    $x++;
	}

	goto query;
	version:
	$new_version = intval($_POST['new_version']);
	$sql = "UPDATE $bugerator_issue_table SET version = \"" . $new_version . "\" ," .
		"updated = '" . date("Y-m-d H:i:s", time()) . "' WHERE id IN " . $selections_to_edit_sql;
	$x = 0;
	$versionsql = "SELECT version_list FROM $bugerator_project_table WHERE id = '" . $results[$x]['project_id'] . "'";
	$version_array = $wpdb->get_var($versionsql);
	$versions = explode(",", $version_array);
	foreach ($selections_to_edit as $this_selection) {
	    if ($new_version <> $results[$x]['version']) {
		$sql_log[] = "INSERT INTO $bugerator_notes_table (issue_id, notes, filename, user, time, hidden ) values (" .
			"'$this_selection', 'Updated version from " . $versions[$results[$x]['version']] .
			" to " . $versions[$new_version] . " .', '0', '$user->ID', " .
			"'" . date("Y-m-d H:i:s", time()) . "', 0 )";
		$log[] = "Updated version from " . $versions[$results[$x]['version']] . " to " .
			$versions[$new_version];
	    } else {
		unset($selections_to_edit[$x]);
	    }
	    $x++;
	}
	goto query;
	delete:

	$sql = "UPDATE $bugerator_issue_table SET  hidden = '1', updated = '" . date("Y-m-d H:i:s", time()) . "'
	    WHERE id IN " . $selections_to_edit_sql;
	foreach ($selections_to_edit as $this_selection) {
	    $sql_log[] = "INSERT INTO $bugerator_notes_table (issue_id, notes, filename, user, time ) values (" .
		    "'$this_selection', 'Deleted issue.', '0', '$user->ID', " .
		    "'" . date("Y-m-d H:i:s", time()) . "' )";
	    $log[] = "Deleted issue.";
	}
	goto query;



	query:
	if (!$wpdb->query($wpdb->prepare($sql)))
	    return array('', 'SQL query failed.');

	// we may not have updated anything.
	if (!isset($sql_log))
	    return array('Nothing to update.', '');
	foreach ($sql_log as $sql) {
	    $wpdb->query($wpdb->prepare($sql));
	}
	$sql = "SELECT name FROM $bugerator_project_table WHERE id = '$project_id'";
	$name = $wpdb->get_var($sql);

	// selections_to_edit potentially has some missing indexes.  Rebuild for the email.
	foreach ($selections_to_edit as $selections) {
	    $select_me[] = $selections;
	}
	$this->email_subscribers($select_me, "", $name, $log, true);
	return array('Update successfull.', '');
    }

    /* get_menu
     * as one would expect this returns our navigation menu
     * for tabs or the like.
     * Tabs: choose show admin add display
     */

    /**
     * This returns the navigation menu at the top of each pade
     * 
     * as one would expect this returns our navigation menu
     * for tabs or the like.
     * Tabs: choose show admin add display
     * 
     * @global type $post
     * @param string $navigation - which page we are getting
     * @param type $choice_menu - if there is a need to choose projects
     * @param type $project - which project is active
     * @param type $issue_id - which issue is active
     * @param type $sample - I'm also calling this from the internal wordpress menu also so this
     *    is making it a sample navigation page instead of a real one.
     * @return string
     */
    function get_menu($navigation, $choice_menu, $project, $issue_id, $sample = "") {
	global $post;
	global $user;
	// need to keep the project moving forward in the get menu if applicable
	// project is overridden by the shortcode anyway
	if ("ALL" == $project)
	    $project = ""; // no project in the get menu anyway
	else
	    $project = "&project=$project";

	// for the css editing the sample fakes the post->guid
	if (!isset($post->guid)) {
	    $post = new stdClass();
	    $post->guid = $sample;
	}

	// $navigation is the current tab
	$tabs = array(
	    "choose" => "Choose Project",
	    "list" => "List Issues",
	    "map" => "Version Map",
	    "add" => "Report Issue",
	    "display" => "Show Detail",
	    "profile" => "Profile",
	    "admin" => "Administration"
	);

	// no point in choosing projects if there is nothing to choose.
	if (false == $choice_menu) {
	    array_shift($tabs);
	    if ("choose" == $navigation)
		$navigation = "show";
	}
	// decide if we show the admin link
	$admin = false;
	if (current_user_can('manage_options'))
	    $admin = true;
	$current_user = $user;
	$admins = explode(",", get_option('bugerator_aditional_admins'));
	if (array_search($current_user->ID, $admins) != false)
	    $admin = true;
	// don't show admin link
	if (false == $admin) {
	    array_pop($tabs);
	}
	// no profile if not logged in.
	if (!isset($user->ID) or $user->ID == 0)
	    array_pop($tabs);
	$issue = "";
	if ($issue_id > 0)
	    $issue = "&issue=$issue_id";


	$output = "<!-- bugerator->get_menu function -->\r\n" .
		"<div class='nav-tab-wrapper bugerator_nav_tab_wrapper' >
		    <h4 class='nav-tab-wrapper bugerator_nav_tab_wrapper' >\r\n";
	foreach ($tabs as $tab => $name) {
	    $class = ($tab == $navigation ) ? " nav-tab-active bugerator_nav_tab_active " : "";
	    $output .= "<a class='nav-tab $class bugerator_nav_tab' href='" .
		    $post->guid . "&bugerator_nav=$tab$project$issue' >$name</a>\r\n";
	}
	// don't know how others do it but this draws the last

	$output .= "</h4></div><!-- nav-tab-wrapper -->\r\n";
	return $output;
    }

    /**
     * Chose a project.
     * 
     * lists the projects and allows the user to choose one
     * if there is only one project then this runs the show_bugs and returns
     * that result * 
     * @global type $path
     * @global type $post
     * @global string $bugerator_project_table
     * @global type $wpdb
     * @param type $my_source
     * @return string
     */
    function choose_project($my_source = "list") {
	global $path;
	global $post;
	global $bugerator_project_table;
	global $wpdb;
	$sql = "SELECT id,name,current_version,status,version_list,version_goal_list FROM $bugerator_project_table WHERE hidden = 0";
	$results = $wpdb->get_results($sql);
	// If we have nothing then um we have nothing
	if (0 == count($results)) {
	    $output = "<h2>Please have an administrator create a project.</h2>";
	    return $output;
	}

	$options = $this->get_options();
	$date_format = $options['date_format'];
	
	// probably not the best way to do this. Get the next version and put it in the object
	for ($x = 0; $x < count($results); $x++) {
	    $version_list = explode(",", $results[$x]->version_list);

	    if ($results[$x]->current_version <> "")
		$results[$x]->thisversion = $version_list[intval($results[$x]->current_version)];
	    else
		$results[$x]->thisversion = "";

	    $version_goal_list = explode(",", $results[$x]->version_goal_list);
	    $key = array_search($results[$x]->thisversion, $version_list);
	    if (isset($version_goal_list[$key + 1])) {
		$results[$x]->next_version = $version_list[$key + 1];
		$results[$x]->next_date = $version_goal_list[$key + 1];
	    } else {
		$results[$x]->next_version = "";
		$results[$x]->next_date = "";
	    }
	}


	$project_statuses = explode(",", get_option('bugerator_project_statuses'));
	// we may show the template from various places so we're changing
	// the template accordingly
	switch ($my_source) {
	    case "list":
		$source = "see issues for.";
		break;
	    case "add":
		$source = "report an issue for.";
		break;
	    // add issues here
	    default:
		$source = "see issues for.";
	}

	ob_start();
	include($path . "bugerator_choose_project_tpl.php");
	$output = ob_get_clean();
	return $output;
    }

    /**
     * Adds a new issue to the system
     * 
     * adds a new issue to the database
     * checking user priviledges and the like
     * 
     * @global string $bugerator_issue_table
     * @global string $bugerator_project_table
     * @global type $wpdb
     * @global type $anonymous_post - if people can post without being logged in
     * @global type $upload_files - if we allow file uploads
     * @global type $path - where includes are - templates
     * @global string $upload_dir - where upload files go
     * @global type $filesize - max limit of a file size based on options.
     * @param type $project - which project
     * @return string
     */
    function add_bug($project) {
	if ("ALL" == $project or '-1' == $project)
	    return $this->choose_project("add");

	global $bugerator_issue_table;
	global $bugerator_project_table;
	global $wpdb;
	global $anonymous_post;
	global $upload_files;
	global $path;
	global $upload_dir;
	global $filesize;
	$error = ""; // to return file errors in addition to output

	if (false == $anonymous_post and
		false == is_user_logged_in())
	    return "<h2>You must register and log in to submit issues.</h2>";



	// more options for admins. Will be true or false
	$admin = $this->is_admin();
	if (true == $admin) {
	    $developers = $this->get_developers($project);
	    // get status messages
	    $statuses_array = explode(",", get_option('bugerator_statuses'));
	    $status_in_use = explode(",", get_option('bugerator_statuses_inuse'));
	    foreach ($status_in_use as $use) {
		$statuses[$use] = $statuses_array[$use];
	    }
	}

	// make sure project number is valid
	$sql = "SELECT name,version_list, options FROM $bugerator_project_table WHERE id = $project";
	$result = $wpdb->get_row($wpdb->prepare($sql));
	$project_name = $result->name;
	if (false == $project_name)
	    return $this->choose_project("add");
	$versions = explode(",", $result->version_list);
	// get the default issue id for the admin add form
	$default_version = 0;
	if ("" != $result->options) {
	    $project_options = $this->get_project_options($project);
	    if (isset($project_options['default_version']))
		$default_version = $project_options['default_version'];
	}


	$types = explode(",", get_option('bugerator_types'));

	$options = $this->get_project_options($project);

	// process form
	if (isset($_POST['add_issue_form']) and
		false !== wp_verify_nonce($_POST['bugerator_add_nonce'], 'bugerator_new')) {
	    $version = "";
	    if (isset($_POST['project_version']))
		$version = intval($_POST['project_version']);
	    else
		$version = $options['default_version'];
	    $priority = "";
	    if (isset($_POST['project_priority']))
		$priority = $_POST['project_priority'];
	    else
		$priority = 3;

	    $now = date("y-m-d H:i:s", time());

	    $submitter_id = get_current_user_id();

	    // this will go in the database.
	    $data = array(
		'project_id' => $project,
		'title' => $_POST['project_title'],
		'description' => $_POST['project_description'],
		'type' => intval($_POST['project_type']),
		'status' => 0,
		'version' => $version,
		'priority' => $priority,
		'submitted' => $now,
		'submitter' => $submitter_id,
		'hidden' => 0
	    );

	    if (isset($_POST['assign']) and intval($_POST['assign']) <> 0)
		$data['assigned'] = intval($_POST['assign']);
	    if (isset($_POST['status']))
		$data['status'] = intval($_POST['status']);

	    // process the file.
	    if ($upload_files) {
		$file = $this->process_file("project_file");
		if (is_array($file)) {
		    $error = $file[1];
		    $data['filename'] = 0;
		} else
		    $data['filename'] = $file;
	    } else {
		// stick a zero there for no file
		$data['filename'] = 0;
	    }


	    // I should probably insert the wordpress way. Here it is. I need the ID anyway.
	    $wpdb->insert($bugerator_issue_table, $data);
	    $issue_id = $wpdb->insert_id;

	    // subscribe the user to the project
	    if (true == is_user_logged_in())
		$this->add_subscription($issue_id, $project);

	    $change = "New issue added.\r\n";
	    $change .= "Title: " . $_POST['project_title'] . ".\r\n" .
		    "Type: " . $types[intval($_POST['project_type'])] . "\r\n" .
		    "Description: " . $_POST['project_description'] . "\r\n";
	    if ($data['filename'] !== 0) {
		$change .= "File attached: " . html_entity_decode(substr($data['filename'], 6)) . "\r\n";
	    }
	    $change = stripslashes($change);
	    $this->email_subscribers($issue_id, $_POST['project_title'], $project_name, $change);

	    $output = "Issue #$issue_id has been added to the system.";
	    // Post successful.  Display the bug.
	    return $this->display_bug($issue_id, $output, $error);
	} else { // form not posted.
	    $nonce = wp_create_nonce('bugerator_new');
	    ob_start();
	    include($path . "bugerator_add_issue_tpl.php");
	    $output = ob_get_clean();
	    return $output;
	}
    }

    /**
     * Process file upload
     * 
     * accept file upload and return the filename or none for the database
     * 
     * @global string $upload_dir
     * @global string $filesize - max filesize determined in options
     * @param type $post_field - what field contains the file information.
     * @return int - yes or no
     */
    function process_file($post_field) {
	global $upload_dir;
	global $filesize;

	// process file if any.
	if (isset($_FILES[$post_field]) and
		0 == $_FILES[$post_field]['error']) { // successfull file load
	    // hurray we have a file.
	    // Make sure it is text or picture
	    $mime = $_FILES[$post_field]['type'];
	    $path_parts = pathinfo($_FILES[$post_field]['name']);
	    $extension = $path_parts['extension'];
	    if ($mime != "text/plain" and
		    $mime != "image/png" and
		    $mime != "image/gif" and
		    $mime != "image/jpeg") {
		return array("", "Invalid file type. File not added. Images and text only.");
	    } elseif ($extension != "jpg" and
		    $extension != "jpeg" and
		    $extension != "gif" and
		    $extension != "png" and
		    $extension != "txt" and
		    $extension != "log") {
		return array("", "Invalid file type. File not added. Valid types are .jpg, .jpeg, .png, .gif, .txt, and .log.");
	    } elseif ($_FILES[$post_field]['size'] > $filesize) {
		return array("", "File too large. File must be under 1MB");
	    } else {
		// stick a 6 digit random number in front of the file name.
		// Should ensure no duplicate files without the fuss and muss
		$file_prefix = sprintf("%0d", mt_rand(1, 999999));
		$full_filename = $file_prefix . $_FILES[$post_field]['name'];
		$full_filename = urlencode($full_filename);
		if (!move_uploaded_file($_FILES[$post_field]['tmp_name'], $upload_dir . "/" . $full_filename)) {
		    return array("", "Unknown file error. Have the administrator make sure $upload_dir exists and is writable");
		    unlink($upload_dir . "/" . $full_filename); // Just make sure nothing is there
		} else {
		    return $full_filename;
		}
	    }
	} elseif (isset($_FILES[$post_field]) and $_FILES[$post_field]['name'] <> "" and
		"" == $_FILES[$post_field]['type']) {// file was attempted but not sent
	    return array("", "File too large. File must be under 1MB");
	} else {
	    return 0;
	}
    }

    /**
     * Displays the information about an issue
     * 
     * displays all of the available details for a certain issue
     * and presents the ability to update status, assign, add notes, etc
     * depending on user access.
     * will use a $_POST call to get the bug or revert to list_issues()
     * $issue_id if provided will override the gett
     * $message and $error will display on the screen
     * $no comments is from the comment edit form and will skip displaying the comments 
     * 
     * @global type $path - where templates are
     * @global type $wpdb
     * @global string $bugerator_issue_table
     * @global string $bugerator_notes_table
     * @global type $date_format - option of how to display date
     * @global type $post - wp post
     * @global string $upload_dir - where to upload files
     * @global string $bugerator_project_table
     * @global type $upload_files - if we allow file uploads
     * @global type $long_date_format - date and time format
     * @global string $bugerator_project_table
     * @param type $issue_id - what number it is
     * @param type $message - Message to display on screen in addition to the issue
     * @param type $error - error message to display on the screen
     * @param type $no_comments - whether we allow comments on issues.
     * @return string
     */
    function display_bug($issue_id = -1, $message = "", $error = "", $no_comments = false) {
	if (-1 == $issue_id and !isset($_GET['issue'])) {
	    return $this->list_issues();
	} else {
	    if (-1 == $issue_id) // no issue was passed so use the get
		$issue_id = intval($_GET['issue']);
	}

	// sometimes there is a get but it is invalid
	if (-1 == $issue_id or "-1" == $issue_id) {
	    if (isset($_GET['project']) and $_GET['project'] > 0)
		return $this->list_issues($_GET['project']);
	    else
		return $this->choose_project();
	}


	// this is the edit button at the bottom of the table. This is the form return
	if (isset($_POST['bugerator_issue_edit']) and
		isset($_POST['bugerator']) and
		false !== wp_verify_nonce($_POST['bugerator'], 'bugerator_edit')) {
	    $post_result = $this->edit_bug();

	    if (is_array($post_result)) {
		$message .= $post_result[0];
		$error .= $post_result[1];
	    } else {
		return $post_result;
	    }
	}

	// this is the Admin edit button at the bottom of the table. This is the form return
	if (isset($_POST['bugerator_admin_issue_edit']) and
		isset($_POST['bugerator_admin']) and
		false !== wp_verify_nonce($_POST['bugerator_admin'], 'bugerator_admin')) {
	    $post_result = $this->edit_bug();

	    if (is_array($post_result)) {
		$message .= $post_result[0];
		$error .= $post_result[1];
	    } else {
		return $post_result;
	    }
	}

	// this is the edit button at the bottom of the table. This is the form return
	if (isset($_POST['bugerator_comment_add']) and
		false !== wp_verify_nonce($_POST['bugerator'], 'bugerator_edit')) {
	    $post_result = $this->add_comment($issue_id);

	    if (is_array($post_result)) {
		$message .= $post_result[0];
		$error .= $post_result[1];
	    } else {
		return $post_result;
	    }
	}

	// This is the subscribe link
	if (isset($_GET['subscribe']) and isset($_GET['nonce']) and
		false !== wp_verify_nonce($_GET['nonce'], 'bugerator_subscribe'))
	    $this->add_subscription($issue_id);


	// there are forms below so create the nonce for them.  Received just above here
	$nonce = wp_create_nonce('bugerator_edit');
	$admin_nonce = wp_create_nonce('bugerator_admin');

	global $path;
	global $wpdb;
	global $bugerator_issue_table;
	global $bugerator_notes_table;
	global $date_format;
	global $post;
	global $upload_dir;
	global $bugerator_project_table;
	global $project_id;

	// get status messages
	$statuses_array = explode(",", get_option('bugerator_statuses'));
	$status_in_use = explode(",", get_option('bugerator_statuses_inuse'));
	foreach ($status_in_use as $use) {
	    $statuses[$use] = $statuses_array[$use];
	}

	// get styles from options and put it together
	$status_backgrounds = explode(",", get_option('bugerator_status_colors'));
	$status_text = explode(",", get_option('bugerator_status_text_colors'));
	for ($x = 0; $x < count($status_text); $x++) {
	    $style[$x] = "background: " . $status_backgrounds[$x] . "; color: " . $status_text[$x] . "; ";
	}
	$types = explode(",", get_option('bugerator_types'));

	// get the options we've set up
	global $upload_files;
	global $long_date_format;
	global $filesize;
	// the bugerator_layouts_tpl.php file defines $issue_table for a generic table
	ob_start();
	include_once($path . "/bugerator_issue_detail_tpl.php");
	$issue_table = ob_get_clean();

	// array of strings to be replaced in $issue_table
	// first is all of the text fields then all of the status fields.
	// In this project I've done a search & replace template and a inserting php project
	// Not sure which one I like better but this has tons of fields and seemed to save typeing
	$search_array = array("_MESSAGE_", "_ERROR_", "_ID_", "_TITLE_", "_STATUS_",
	    "_VERSION_", "_PRIORITY_", "_ASSIGNED_USER_",
	    "_SUBMITTED_USER_", "_FILE_ATTACHED_", "_TYPE_", "_SUBMITTED_DATE_",
	    "_UPDATED_", "_DESCRIPTION_", "TITLE_STYLE",
	    "_FILE_TEXT_", "_JAVASCRIPT_");

	$sql = "SELECT * FROM $bugerator_issue_table WHERE id = '$issue_id'";
	$results = $wpdb->get_results($wpdb->prepare($sql));
	if (!$results) {
	    return "Invalid issue ID#.";
	}

	// go through the table results and prepare the replace array
	$result = $results[0];
	//$project_id = $result->project_id;
	if ($result->assigned > 0) {
	    $assigned = new wp_user($result->assigned);
	    $assigned_name = $assigned->display_name;
	} else {
	    $assigned_name = "";
	}
	$submitter = new wp_user($result->submitter);


	if ($result->filename <> "0") {
	    $file_text = "<span id='file_attach_issue_$issue_id'></span>";
	    $file_attached = "<a onclick = 'show_file_issue_$issue_id();'>" .
		    urldecode(substr($result->filename, 6)) . "</a>";
	    $javascript = $this->display_attachment('issue', $issue_id, $result->filename);
	} else {
	    $file_text = "";
	    $file_attached = "";
	    $javascript = "";
	}

	if (strtotime($result->updated) <> "")
	    $update_date = date($long_date_format, strtotime($result->updated));
	else
	    $update_date = "";
	$sql = "SELECT version_list from $bugerator_project_table WHERE id = '$project_id'";
	$versions = explode(",", $wpdb->get_var($sql));

	// show a subscribe link if appropriate next to the id number.
	$output_id = $result->id;
	if (false == $this->check_subscription($issue_id)) {
	    $subscribe_nonce = wp_create_nonce('bugerator_subscribe');
	    $output_id .= " <a href=$post->guid&bugerator_nav=display&project=$project_id" .
		    "&issue=$issue_id&subscribe=true&nonce=$subscribe_nonce " .
		    "class='bugerator bugerator_issue_detail' > Click to subscribe.</a>";
	} else {
	    $output_id .= " Subscribed";
	}

	$db_output = array(
	    $message,
	    $error,
	    $output_id,
	    $result->title,
	    $statuses[$result->status],
	    $versions[$result->version],
	    $result->priority,
	    $assigned_name,
	    $submitter->display_name,
	    $file_attached,
	    $types[$result->type],
	    date($long_date_format, strtotime($result->submitted)),
	    $update_date,
	    nl2br($result->description),
	    $style[$result->status], // title style
	    $file_text,
	    $javascript
	);
	$output = stripslashes(str_replace($search_array, $db_output, $issue_table));

	//Don't want all of these forms if we are eiditing a comment.
	if (!$no_comments) {
	    // the developer edit section
	    if ($this->is_developer($project_id) or $this->is_admin($project_id)) {
		$developers = $this->get_developers($project_id);

		ob_start();
		include_once($path . "/bugerator_issue_edit_tpl.php");
		$edit_option = ob_get_clean();
		$output .= $edit_option;
	    }

	    // for the comment display so we don't have to rerun the admin test.
	    $im_an_admin = false;
	    // the admin edit section
	    if ($this->is_admin($project_id)) {
		$im_an_admin = true;
		$developers = $this->get_developers($project_id);
		global $bugerator_project_table;
		$sql = "SELECT version_list from $bugerator_project_table WHERE id = '$project_id'";
		$versions = explode(",", $wpdb->get_var($sql));
		ob_start();
		include_once($path . "/bugerator_issue_admin_edit_tpl.php");
		$admin_option = ob_get_clean();
		$output .= $admin_option;
	    }



	    // the add a comment section
	    ob_start();
	    include_once("$path/bugerator_comment_form_tpl.php");
	    $output .= ob_get_clean();
	}

	// Display comments
	// The comment edit form can request that the comments not be shown
	if (!$no_comments) {
	    $sql = "SELECT * FROM $bugerator_notes_table WHERE issue_id = '$issue_id' and hidden = 0";
	    $comments = $wpdb->get_results($wpdb->prepare($sql));
	    if (is_array($comments)) {

		ob_start();
		include("$path/bugerator_comment_display_tpl.php");
		$output .= ob_get_clean();
	    }
	}

	return $output;
    }

    /**
     * Shows an attachment
     * 
     * Takes in $type = issue or note for an issue attachment or note attachment
     * then the id and creates the appropriate javascipt
     * filename is the filename. duh
     *  
     * @global string $upload_dir - where attachments are
     * @param type $type - image or text
     * @param type $id - which issue
     * @param type $filename
     * @return string
     */
    function display_attachment($type, $id, $filename) {
	global $upload_dir;
	$ajax_nonce = wp_create_nonce('bugerator_get_attachment');
	$dir = wp_upload_dir();

	if ("txt" == strtolower(substr($filename, -3)) or
		"log" == strtolower(substr($filename, -3))) {

	    // using an ajax call to get the text file
	    $javascript = " // quickie ajax to get the attachment
function show_file_$type" . "_$id() {
	var data = {
	    action: 'bugerator_get_attachment',
	    post: '$type',
	    id: $id,
	    security: '$ajax_nonce'
	};
	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.get(ajaxurl, data, function(response) {
	    document.getElementById('file_attach_$type" . "_$id').innerHTML = response;

	});
		    }";
	} else {
	    $ajax_url = admin_url() . "admin-ajax.php";
	    // just adding an imageurl to get the picture

	    $javascript = "function show_file_$type" . "_$id() {
		    document.getElementById('file_attach_$type" . "_$id').innerHTML =
		    '<img src=\"$ajax_url?action=bugerator_get_attachment" .
		    "&security=$ajax_nonce&post=$type&id=$id\" >'
 }";
	}
	return $javascript;
    }

    /**
     * Process the edit issue form
     * 
     * processes the edit bug form
     * returns success or fail message.
     *  
     * @global type $project_id
     * @global string $bugerator_issue_table
     * @global string $bugerator_project_table
     * @global type $wpdb
     * @global type $user - our user information
     * @global string $upload_dir
     * @return type
     */
    function edit_bug() {
	global $project_id;
	global $bugerator_issue_table;
	global $bugerator_project_table;
	$new_description = ""; // for the email output
	global $wpdb;
	// see if current user can do this
	if (!$this->is_developer($project_id) and
		!$this->is_admin())
	    return array('', 'Invalid User');
	global $user;
	$id = $user->ID;
	// get posted info
	$issue_id = intval($_POST['issue_id']);
	$status = intval($_POST['status']);
	$priority = intval($_POST['priority']);
	$assign = intval($_POST['assign']);
	$version = intval($_POST['version']);
	// as they say reading is fast and writing is slow.
	$sql = "SELECT * FROM $bugerator_issue_table WHERE id = '$issue_id'";
	$row = $wpdb->get_row($sql, ARRAY_A);
	$data = array();
	$log_output = "";
	if ($row['status'] != $status) {
	    $data['status'] = $status;
	    $statuses = explode(",", get_option('bugerator_statuses'));
	    $log_output .= "Updated status to " . $statuses[$status] . " from " . $statuses[$row['status']] . ".\r\n";
	}
	if ($row['priority'] != $priority) {
	    $data['priority'] = $priority;
	    $log_output .= "Updated priority to $priority from " . $row['priority'] . ".\r\n";
	}
	if ($row['assigned'] != $assign) {
	    $data['assigned'] = $assign;
	    $olduser = get_userdata($row['assigned']);

	    if ($assign > 0) {
		$thisuser = get_userdata($assign);
		if (!isset($olduser->display_name))
		    $log_output .= "Assigned to $thisuser->display_name.\r\n";
		else
		    $log_output .= "Assigned to $thisuser->display_name from $olduser->display_name.\r\n";
	    } else {
		$log_output .= "Removed assignment.\r\n";
	    }
	}
	if ($row['version'] != $version) {
	    $sql = "SELECT version_list from $bugerator_project_table WHERE id = '" . $row['project_id'] . "'";
	    $versions = $wpdb->get_row($sql);
	    $versions = explode(",", $versions->version_list);
	    $data['version'] = $version;
	    $log_output .= "Updated version to " . $versions[$version] . " from " . $versions[$row['version']] . ".\r\n";
	}
	// now the extra results from the admin edit
	if (true == $this->is_admin() and isset($_POST['bugerator_admin_issue_edit'])) {
	    global $upload_dir;
	    // effectively delete by hiding the issue.
	    if (isset($_POST['hide_issue']) and "yes" == $_POST['hide_issue']) {
		$data['hidden'] = 1;
		$log_output .= "Issue hidden.\r\n";
	    }
	    if (isset($_POST['delete_file']) and "yes" == $_POST['delete_file']) {
		$data['filename'] = 0;
		unlink($upload_dir . "/" . $row['filename']);
		$log_output .= substr($row['filename'], 6) . " was deleted.\r\n";
	    }
	    $title = $_POST['title'];
	    if ($row['title'] != $title and $title <> "") {
		$data['title'] = $title;
		$log_output .= "Changed title to $title\r\n";
	    }


	    if ($row['description'] != $_POST['description']) {
		$data['description'] = $_POST['description'];
		$log_output .= "Edited description.\r\n";
		$new_description = $data['description'];
	    }
	}

	if (count($data) > 0) {
	    $data['updated'] = date("Y-m-d H:i:s", time());
	    if (!$wpdb->update($bugerator_issue_table, $data, array('id' => $issue_id)))
		return array('', 'Update failed.');
	}

	if ($log_output <> "") {
	    $this->add_comment($issue_id, $log_output);
	    $sql = "SELECT name FROM $bugerator_project_table WHERE id = '$project_id'";
	    $project_name = $wpdb->get_var($sql);
	    $change = $log_output . $new_description;
	    $this->email_subscribers($issue_id, $row['title'], $project_name, $change);
	}
	return array('Update successfull.', '');
    }

    /**
     * Adds a comment to an issue
     * 
     * processes the add comment form
     * returns success or fail message.
     * @global type $project_id
     * @global string $bugerator_notes_table
     * @global type $wpdb
     * @global type $upload_files
     * @global type $user
     * @param type $comment
     * @param type $issue_id
     * @return type
     */
    function add_comment($issue_id, $comment = "") {
	global $project_id;
	global $bugerator_notes_table;
	global $bugerator_issue_table;
	global $bugerator_project_table;
	global $wpdb;
	global $upload_files;
	global $user;
	$error = "";

	$data['user'] = $user->ID;

	// This is for the bug change loggin. It will be called internally.
	if ($comment <> "") {
	    $data['notes'] = $comment;
	    $internal = true;
	} else {
	    // get posted info
	    $data['notes'] = $_POST['comments'];
	    $internal = false;
	}
	$data['issue_id'] = $issue_id;
	// see if there is a file and process / add the filename or add 0
	if ($upload_files) {
	    $filename = $this->process_file("comment_file");
	    if (is_array($filename)) {
		// get error message if it is an array. There is no file
		$error = $filename[1];
		$data['filename'] = 0;
	    } else {
		$data['filename'] = $filename;
	    }
	} else {
	    $data['filename'] = 0;
	}

	// What time is it?
	$data['time'] = date("Y-m-d H:i:s", time());
	// set hidden (deleted) to zero
	$data['hidden'] = 0;


	if (!$wpdb->insert($bugerator_notes_table, $data))
	    return array('', 'Comment update failed.');

	// need to email update.
	if (false == $internal) {
	    $sql = "SELECT title, project_id FROM $bugerator_issue_table WHERE id = '$issue_id'";
	    $output = $wpdb->get_row($sql);
	    $sql = "SELECT name FROM $bugerator_project_table WHERE id = '$output->project_id'";
	    $project_name = $wpdb->get_var($sql);
	    $change = "Comment added.\r\n";
	    $change .= "Title: $output->title.\r\n" .
		    "Comment: " . $data['notes'] . "\r\n";
	    if ($data['filename'] !== 0) {
		$change .= "File attached: " . html_entity_decode(substr($data['filename'], 6)) . "\r\n";
	    }
	    $change = stripslashes($change);
	    $this->email_subscribers($issue_id, $output->title, $project_name, $change);
	}
	return array('Comment added.', $error);
    }

    /**
     * Edits a comment
     * 
     * allows the admin/developer to edit a contributed comment
     * 
     * @global string $bugerator_notes_table
     * @global type $wpdb
     * @global type $long_date_format
     * @global string $upload_dir
     * @return type
     */
    function edit_comment() {

	$issue_id = intval($_GET['issue']);
	global $bugerator_notes_table;
	if (false == $this->is_admin())
	    return $this->display_bug($issue_id, "", "Unable to edit comment.");

	global $wpdb;
	global $long_date_format;
	global $upload_dir;
	// delete the comment referenced in the get
	if (isset($_GET['comment_delete'])) {
	    $comment_to_delete = intval($_GET['comment_delete']);
	    $data['hidden'] = 1;
	    $where['id'] = $comment_to_delete;
	    $sql = "SELECT filename FROM $bugerator_notes_table WHERE id = $comment_to_delete";
	    $filedelete = $wpdb->get_var($sql);
	    if ($filedelete <> "0")
		unlink($upload_dir . "/" . $filedelete);
	    if (!$wpdb->update($bugerator_notes_table, $data, $where))
		return $this->display_bug($issue_id, "", "Comment delete failed.");
	    else
		return $this->display_bug($issue_id, "Comment deleted");
	}
	$comment_id = intval($_GET['comment_edit']);
	$sql = "SELECT * FROM $bugerator_notes_table WHERE id = '$comment_id'";
	$row = $wpdb->get_row($sql);

	if (isset($_POST['comment_edit_finished'])) {
	    if (isset($_POST['bugerator_comment_edit'])) {
		$nonce = $_POST['bugerator_comment_edit'];
		if (!wp_verify_nonce($nonce, 'bugerator_comment_edit')) {
		    return $this->display_bug($issue_id);
		}
		$data = array();
		$log = "";
		$now = date($long_date_format, time());
		if ($row->user != $_POST['comment_author']) {
		    $data['user'] = intval($_POST['comment_author']);
		    $log .= "-- Author changed. $now\r\n";
		}
		if ($row->notes != $_POST['comment_new']) {
		    $data['notes'] = $_POST['comment_new'];
		    $log .= "-- Comment edited. $now\r\n";
		}
		if (isset($_POST['delete_file'])) {
		    if (!unlink($upload_dir . "/" . $row->filename))
			;
		    $log .= "-- " . substr($row->filename, 6) . " was deleted. $now\r\n";
		    $data['filename'] = 0;
		}
		if (!isset($_POST['secret_edit'])) {
		    if (isset($data['notes']))
			$data['notes'] .= "\r\n" . $log;
		    else
			$data['notes'] = "\r\n" . $log;
		}
		$where['id'] = $comment_id;
		if (count($data) > 0) {
		    if (!$wpdb->update($bugerator_notes_table, $data, $where))
			return $this->display_bug($issue_id, "", "Update failure.");
		}
		return $this->display_bug($issue_id, "Comment edited successfully.");
	    }
	}


	// get the bug display without comments
	$output = $this->display_bug($issue_id, "", "", true);

	$user = $row->user;

	$sql = "SELECT ID, display_name FROM " . $wpdb->users;
	$results = $wpdb->get_results($sql);
	if (null == $row)
	    return $this->display_bug($issue_id, "", "Comment does not exist.");

	$comment_nonce = wp_create_nonce('bugerator_comment_edit');

	ob_start();
	include_once('bugerator_comment_edit_tpl.php');
	$output .= ob_get_clean();
	return $output;
    }

    /**
     * Decide if the current user is an developer.
     * 
     * Decide if the current user is an developer.
     * 
     * @global string $bugerator_project_table - developers can be for just one project
     * @global type $wpdb
     * @global type $user - current user information
     * @param type $project_id - which project
     * @return boolean
     */
    function is_developer($project_id) {
	global $bugerator_project_table;
	global $wpdb;
	$developers = explode(",", get_option('bugerator_developers'));
	$sql = "SELECT developers FROM $bugerator_project_table WHERE id = '$project_id'";
	$project_developers = $wpdb->get_var($sql);
	if (!is_array($project_developers))
	    $project_developers = array();
	global $user;
	$id = $user->ID;
	if (false == array_search($id, $developers) and
		false == array_search($id, $project_developers)) {
	    return false;
	}
	return true;
    }

    /**
     * Returns a list of developers
     * 
     * Returns an array of developers for the current project
     * including display_names
     * 
     * @global string $bugerator_project_table
     * @global type $wpdb
     * @param type $project_id - which project
     * @return type
     */
    function get_developers($project_id) {
	global $bugerator_project_table;
	global $wpdb;
	$developers = explode(",", get_option('bugerator_developers'));
	$admins = explode(",", get_option('bugerator_admins'));
	$sql = "SELECT developers FROM $bugerator_project_table WHERE id = '$project_id'";
	$project_developers = $wpdb->get_var($sql);
	$sql = "SELECT admins FROM $bugerator_project_table WHERE id = '$project_id'";
	$project_admins = $wpdb->get_var($sql);
	if (!is_array($project_developers))
	    $project_developers = array();
	if (!is_array($project_admins))
	    $project_admins = array();
	$return_array = array_unique(array_merge($developers, $admins, $project_developers, $project_admins));
	// for some reason getting a blank spot in the middle. Get rid of it here
	$y = 0;
	for ($x = 0; $x < count($return_array); $x++) {
	    if ($return_array[$x] <> "") {
		$output_array[$y]['id'] = $return_array[$x];
		$user = get_userdata($return_array[$x]);
		$output_array[$y]['name'] = $user->display_name;
		$y++;
	    }
	}
	return $output_array;
    }

    /**
     * Edit the users subscriptions to things
     * 
     * Allows you to cancel projects and issue's your subscribed to. Can also subscribe to the current project.
     * 
     * @global type $user
     * @global string $bugerator_subscriptions
     * @global string $bugerator_project_table
     * @global string $bugerator_issue_table
     * @global type $project_id
     * @global type $wpdb
     * @global type $post
     * @return string
     */
    function profile() {
	global $user;
	if (!isset($user->ID) or $user->ID == 0)
	    return "You must be logged in.";
	// get the table and the current project
	global $bugerator_subscriptions;
	global $bugerator_project_table;
	global $bugerator_issue_table;
	global $project_id;
	global $wpdb;
	global $post;
	global $path;
	// screen message
	$message = "";
	$nonce = wp_create_nonce('bugerator_profile');
	$profile_dir = admin_url() . "profile.php";

	$current_project = false;

	// see if the user want to get all emails or only one email until they log in again.
	$users_all_emails = explode(",", get_option('bugerator_subscribers_all_email'));
	if (false == array_search($user->ID, $users_all_emails)) {
	    $all = " ";
	    $one = " checked ";
	} else {
	    $all = " checked ";
	    $one = "";
	}


	// before getting subscriptions we need to make sure we add/subtract existing ones.
	// unsubscribe
	if (isset($_GET['unsubscribe'])) {
	    $type = $_GET['unsubscribe'];
	    $id = intval($_GET['id']);

	    if ("Projects" != $type and "Issues" != $type)
		goto get_subscriptions;
	    // make sure nobody is faking unsubscriptions
	    $sql = "SELECT user FROM $bugerator_subscriptions WHERE id = '$id'";
	    $this_user = $wpdb->get_var($sql);
	    if ($this_user != $user->ID) // somebody is messing with us on the get line
		goto get_subscriptions;
	    $sql = "DELETE FROM $bugerator_subscriptions WHERE id = '$id'";
	    $wpdb->query($sql);
	    $message .= "<h3 class='bugerator'>Subscription Canceled. Log in to the " .
		    strtolower(substr($type, 0, -1)) . " to resubscribe.</h3>";
	}


	get_subscriptions:

	// Below the select so we can make sure there are no duplicates
	if (isset($_GET['subscribe'])) {
	    $id = intval($_GET['id']);
	    $duplicate = false;
	    $sql = "SELECT COUNT(id) from $bugerator_subscriptions WHERE foreign_id = '$id'" .
		    " and user = '$user->ID'";
	    $result = $wpdb->get_var($sql);

	    // Yay somebody is subscribing.
	    if (0 == $result) {
		$data = array(
		    "user" => $user->ID,
		    "type" => "Projects",
		    "foreign_id" => intval($_GET['id']),
		    "visited" => 1
		);
		$wpdb->insert($bugerator_subscriptions, $data);
		$sql = "SELECT id FROM $bugerator_issue_table WHERE project_id = '$project_id'";
		$results = $wpdb->get_results($sql);
		foreach ($results as $result) {
		    $issue_list[] = $result->id;
		}
		if (isset($issue_list)) {
		    $issues = implode(",", $issue_list);
		    $sql = "DELETE FROM $bugerator_subscriptions WHERE user = '$user->ID' " .
			    " and type='Issues' and foreign_id IN ($issues)";
		    $wpdb->query($sql);
		}
	    }
	    $message .= "<h3 class='bugerator'>Subscription added.</h3>";
	}

	$sql = "SELECT id, type, foreign_id from $bugerator_subscriptions WHERE user = $user->ID ORDER BY " .
		"FIELD(type,'Projects','Issues'), foreign_id DESC";
	$results = $wpdb->get_results($sql);

	// figure out if we are already subscribed to the current project
	$subscribed = array();

	if (is_array($results)) {
	    foreach ($results as $row) {
		if ("Projects" == $row->type) {
		    $subscribed[] = $row->foreign_id;
		}
	    }
	}

	if (false !== array_search($project_id, $subscribed)) {
	    $current_project = true;
	}

	// get the names of the projects and the titles for the template.
	$sql = "SELECT name, id FROM $bugerator_project_table";
	$project_name_array = $wpdb->get_results($sql);
	$sql = "SELECT title, id FROM $bugerator_issue_table";
	$issue_name_array = $wpdb->get_results($sql);
	foreach ($project_name_array as $pn) {
	    $project_names[$pn->id] = $pn->name;
	}
	foreach ($issue_name_array as $in) {
	    $issue_names[$in->id] = $in->title;
	}



	// if we aren't subscribed offer to subscribe to the current project.
	if (false === $current_project and $project_id >= 0) {
	    $sql = "SELECT name FROM $bugerator_project_table WHERE id='$project_id'";
	    $project_name = $wpdb->get_var($sql);
	    $subscribe_message = "<h4 class='bugerator'><a href=$post->guid&bugerator_nav=profile" .
		    "&project=$project_id&subscribe=Projects&id=$project_id&nonce=$nonce " .
		    ">Click to subscribe to $project_name.</a></h4>";
	} else {
	    $subscribe_message = "";
	}
	ob_start();
	include("$path/bugerator_user_profile_tpl.php");
	$output = ob_get_clean();
	return $output;
    }

    /**
     * Admin functions like edit options, css, etc
     * 
     * gives an admin menu to do things like create projects, promote user status,etc.  Basically 
     * every global option is here.
     * 
     * @global type $post - page information
     * @global string $bugerator_project_table
     * @global type $path
     * @return type
     */
    function admin() {
	global $post;
	global $bugerator_project_table;
	$self = $post->guid;
	global $path;

	$error_msg = ""; // define variable that will display an error on the template
	$success_msg = ""; // same for success messages;
	// See if they should see this menu
	if (false == $this->is_admin())
	    return $this->choose_project();

	$current_user = wp_get_current_user();


	// assign our tab pages. Will be using Javascript for these for quick action
	$admin_tabs = array(
	    "projects" => "Projects",
	    "users" => "Users",
	    "db" => "Database Maintenance",
	    "options" => "Options",
	    "change_css" => "Appearance / CSS"
	);

	$active_tab = "projects";
	if (isset($_GET['active_tab']) and array_key_exists($active_tab, $admin_tabs)) {
	    $active_tab = $_GET['active_tab'];
	}
	// get statuses for a drop down menu
	$statuses = explode(",", get_option('bugerator_project_statuses'));
	$status_array = explode(",", get_option('bugerator_project_statuses_inuse'));
	foreach ($status_array as $use_me) {
	    $project_statuses[] = $statuses[$use_me];
	}
	// deal with ajax crap
	$nonce = wp_create_nonce('bugerator');

	// export the database
	if (isset($_GET['export_db_or_else'])) {
	    $outarray = $this->admin_db_export();
	    // if it fails just pass it on
	    if (!is_array($outarray))
		return $outarray;
	    $success_msg = $outarray[0];
	    $error_msg = $outarray[1];
	    if (isset($outarray[2])) {
		// Sometimes we want a message that isn't so big
		$status_msg = $outarray[2];
	    }
	}

	// import the database
	if (isset($_GET['kill_and_import_db'])) {
	    $outarray = $this->admin_db_import();
	    // form input and the like.
	    if (!is_array($outarray))
		return $outarray;
	    $success_msg = $outarray[0];
	    $error_msg = $outarray[1];
	    if (isset($outarray[2])) {
		// Sometimes we want a message that isn't so big
		$status_msg = $outarray[2];
	    }
	}


	// Desicion time: deal with posted data and go from there.
	// This is the adding a new project screen
	if (isset($_POST['new_project_form']) and "yes" == $_POST['new_project_form']
		and false !== wp_verify_nonce($_POST['new_project_nonce'], 'bugerator_new_project')) {
	    $messages = $this->admin_add_project();
	    $success_msg = $messages[0];
	    $error_msg = $messages[1];
	}

	// now we need to show the edit project form
	if (isset($_GET['bugerator_edit_project']) or
		isset($_POST['bugerator_edit_project'])) {
	    $messages = $this->admin_edit_project();
	    if (is_array($messages)) {
		$status_msg = $messages[0];
		$error_msg = $messages[1];
	    } else {
		return $messages;
	    }
	}

	// if we choose to edit the css we need to break out of the ajax tabs.
	if ("change_css" == $active_tab) {
	    return $this->admin_change_css($admin_tabs);
	}

	// load the javascript
	wp_enqueue_script("bugerator_admin", plugins_url("bugerator_admin.js", __FILE__));

	// get the options page from the menu
	$options_page = BugeratorMenu::bugerator_option_general();
	$users_page = BugeratorMenu::bugerator_edit_users();

	ob_start();
	include_once($path . "/bugerator_admin_nav_tpl.php");
	include_once($path . "/bugerator_admin_tpl.php");
	$admin_output = ob_get_clean();

	return $admin_output;
    }

    /**
     * Processes the new project 
     * 
     * This processes the new project screen and adds the project into the database
     * @global type $wpdb
     * @global string $bugerator_project_table
     * @return type
     */
    function admin_add_project() {
	global $wpdb;
	global $bugerator_project_table;
	$success_msg = "";
	$error_msg = "";
	$status_array = explode(",", get_option('bugerator_project_statuses_inuse'));

	$project_name = $_POST['project_name'];

	$sql = "SELECT COUNT(*) FROM " . $bugerator_project_table . " WHERE name = '$project_name'";
	$project_count = $wpdb->get_var($wpdb->prepare($sql));
	if ($project_count > 0) {
	    $error_msg = "Project name already exists.";
	    return array('', $error_msg);
	}
	$date = strtotime($_POST['next_version_date']);
	$version_date = date("m-d-Y", $date);
	if ("" == $_POST['project_owner']) {
	    $owner = new WP_user($_POST['user']);
	} else {
	    $project_owner = $wpdb->prepare($_POST['project_owner']);
	    $sql = "SELECT ID from $wpdb->users WHERE display_name = '$project_owner'";
	    $result = $wpdb->get_var($sql);
	    $owner = new WP_user($result);
	}
	$current_version = $_POST['current_version'];
	$next_version = $_POST['next_version'];
	$version_list = array();
	$version_created_list = array();
	$goal_date_list = array();
	$current_date = date("Y-m-d", time());
	$goal_date = date("Y-m-d", strtotime($_POST['next_version_date']));
	$version = "";
	if ($current_version <> "") {
	    $version_list[] = $current_version;
	    $version_created_list[] = $current_date;
	    $goal_date_list[] = $current_date;
	    $version = 0;
	}
	if ($next_version <> "") {
	    $version_list[] = $next_version;
	    $version_created_list[] = $current_date;
	    $goal_date_list[] = $goal_date;
	}
	$version_list = implode(",", $version_list);
	$version_created_list = implode(",", $version_created_list);
	$goal_date_list = implode(",", $goal_date_list);

	$sql = "INSERT INTO $bugerator_project_table ( name, owner, " .
		"current_version, version_date, status, version_list, version_created_list,
                    version_goal_list, admins, hidden) VALUES ( '$project_name', '$owner->ID', '$version', " .
		"'$current_date', '" . $status_array[$_POST['project_status']] . "', " .
		"'$version_list', '$version_created_list', '$goal_date_list', '$owner->ID', '0' );";
	$result = $wpdb->query($sql);
	if (1 == $result)
	    $success_msg = "Project succesfully added";
	else
	    $error_msg = "Database failed to add project.";
	return array($success_msg, $error_msg);
    }

    /**
     * show the project edit form and process the results;
     * 
     * show the project edit form and process the results;
     * 
     * @global type $path
     * @global type $wpdb
     * @global string $bugerator_project_table
     * @global type $post
     * @return type
     */
    function admin_edit_project() {
	// Get all of the project information and prepare it for the edit form.
	global $path;
	global $wpdb;
	global $bugerator_project_table;
	global $post;
	$options = $this->get_options();
	$date_format = $options['date_format'];

	// load the javascript
	wp_enqueue_script("bugerator_admin", plugins_url("bugerator_admin.js", __FILE__));
	$error_msg = "";
	$success_msg = "";
	$admin_tabs = array(
	    "projects" => "Projects",
	    "issues" => "Issues",
	    "users" => "Users",
	    "db" => "Database Maintenance",
	    "options" => "Options",
	    "change_css" => "Appearance / CSS"
	);
	$active_tab = "projects";

	$nonce = wp_create_nonce('bugerator_admin_edit_project');

	// get option values
	$statuses_array = explode(",", get_option('bugerator_project_statuses'));
	$statuses_inuse = explode(",", get_option('bugerator_project_statuses_inuse'));
	foreach ($statuses_inuse as $status)
	    $statuses[$status] = $statuses_array[$status];

	if (isset($_GET['bugerator_edit_project']))
	    $project_id = $_GET['bugerator_edit_project'];

	$sql = "SELECT * FROM $bugerator_project_table WHERE id = '$project_id' ";
	$result = $wpdb->get_row($wpdb->prepare($sql));
	$output = "";

	if (isset($_POST['bugerator_admin_edit_project'])) {
	    $project_id = $_POST['bugerator_admin_edit_project'];
	    if (wp_verify_nonce($_POST['bugerator_admin_edit_nonce'], 'bugerator_admin_edit_project')) {
		return $this->process_project_edit($project_id, $result);
	    } else {
		return array('', "Nonce failure. Sorry.");
	    }
	}


	$sql = "SELECT ID,display_name from " . $wpdb->users;
	$name_array = $wpdb->get_results($sql, ARRAY_N);
	foreach ($name_array as $names) {
	    $display_names[$names[0]] = $names[1];
	}

	// get list of versions
	if ($result->version_list <> "") {
	    foreach (explode(",", $result->version_list) as $key => $value) {
		$version_list[$key] = $value;
	    }
	} else {
	    $version_list = array();
	}

	// get list of version dates
	if ($result->version_goal_list <> "") {
	    foreach (explode(",", $result->version_goal_list) as $key => $value) {
		$version_goal_list[$key] = $value;
	    }
	}
	$project_options = BugeratorMain::get_project_options($project_id);
	if (isset($project_options['default_version']))
	    $result->default_version = $project_options['default_version'];
	else
	    $result->default_version = "";


	$edit_link = "";

	ob_start();
	include_once($path . "/bugerator_admin_edit_project_tpl.php");
	$output = ob_get_clean();
	return $output;
    }

    /**
     * Sets up the css edit menu and displays it on the regular admin screen
     * 
     * Sets up the css edit menu and displays it on the regular admin screen
     * @global type $post
     * @global type $path
     * @param type $tabs
     * @return type
     */
    function admin_change_css($tabs) {
	global $post;
	global $path;
	$page = $post->guid;

	if (isset($_GET['project']))
	    $project = intval($_GET['project']);
	else
	    $project = "all";

	if (isset($_GET['subtab'])) {
	    $subnavigation = $_GET['subtab'];
	} else {
	    $subnavigation = "all";
	}

	// first see if we've had something submitted
	BugeratorMenu::check_for_color_changes();

	$_GET['tab'] = "change_css";
	$subtabs = BugeratorMenu::get_subtabs();

	$content = BugeratorMenu::bugerator_option_css($subnavigation);
	$navigation = "change_css";
	ob_start();
	include("$path/bugerator_options_page_tpl.php");
	$output = ob_get_clean();

	return $output;
    }

    /**
     * Processes the process edit
     * 
     * Processes the information posted to edit a project.
     * @global type $wpdb
     * @global string $bugerator_project_table
     * @param type $project_id
     * @param type $result
     * @return type
     */
    function process_project_edit($project_id, $result) {
	global $wpdb;
	global $bugerator_project_table;
	$project_id = intval($project_id);
	$sql = "SELECT ID from $wpdb->users WHERE display_name = '" . $_POST['project_owner'] . "'";
	$owner_id = $wpdb->get_var($wpdb->prepare($sql));
	// sometimes a bad name is passed.  ignore it.
	if ("0" == $owner_id or 0 == $owner_id or 0 == intval($owner_id))
	    $owner_id = $result->owner;

	// put together the insert array so we don't insert more than we need to
	if ($result->name <> $_POST['project_name'])
	    $row['name'] = $_POST['project_name'];
	if ($result->owner <> $owner_id) {
	    $row['owner'] = $owner_id;
	    $admins = explode(",", $result->admins);
	    if (is_array($admins) and $admins[0] <> "") {
		if (false == array_search($owner_id, $admins)) {
		    $admins[] = $owner_id;
		    $row['admins'] = implode(",", $admins);
		}
	    } else {
		$row['admins'] = $owner_id;
	    }
	}
	if ($result->status <> $_POST['project_status'])
	    $row['status'] = $_POST['project_status'];

	// no goal dates without a version date so we need to shorten goal array
	for ($x = 0; $x < count($_POST['version_array']); $x++) {
	    if ($_POST['version_array'][$x] <> "")
		$version_array[] = $_POST['version_array'][$x];
	}
	$goal_array = $_POST['goal_dates'];
	$version_created_list = explode(",", $result->version_created_list);


	// we have version array and goal array. Now time to decide if we are killing a version.
	if (isset($_POST['version_goal_delete'])) {
	    $version_delete = $_POST['version_goal_delete'];
	    foreach ($version_delete as $deleteme) {
		// reduce the version number by once since the array changed
		if ($deleteme <= $_POST['current_version']) {
		    if ($_POST['current_version'] > 0)
			$_POST['current_version']--;
		}
		// rebuild the array with the element extracted
		for ($x = 0; $x < count($version_array); $x++) {
		    if ($x == $deleteme)
			continue;
		    $new_version_array[] = $version_array[$x];
		    $new_goal_array[] = $goal_array[$x];
		    $new_version_created_list[] = $version_created_list[$x];
		}
		$version_array = $new_version_array;
		$goal_array = $new_goal_array;
		$version_created_list = $new_version_created_list;
		unset($new_goal_array, $new_version_array, $new_version_created_list);
		// not automatically set later so we have to do it here
		$row['version_created_list'] = implode(",", $version_created_list);
	    }
	}
	if (isset($_POST['current_version']) and $result->current_version <> $_POST['current_version'])
	    $row['current_version'] = $_POST['current_version'];
	if (isset($_POST['default_version']) and $result->current_version <> $_POST['default_version']) {
	    $options = $this->get_project_options($project_id);
	    if ("" == $_POST['default_version'])
		$options['default_version'] = "";
	    else
		$options['default_version'] = intval($_POST['default_version']);
	    foreach ($options as $key => $value) {
		$option_out[] = "$key|$value";
	    }
	    $options_out = implode(",", $option_out);
	    $row['options'] = $options_out;
	}

	if (isset($version_array)) {
	    while (count($goal_array) > count($version_array)) {
		array_pop($goal_array);
	    }


	    $version_list = implode(",", $version_array);
	    $goal_dates = implode(",", $goal_array);
	    if ($version_list <> $result->version_list) {
		$row['version_list'] = $version_list;
		$count = 0;
		// need to add today's date to the version created list
		foreach ($version_array as $versions) {
		    if ($versions <> "")
			$count++;
		}

		$new_version_created = 0;
		for ($x = 0; $x < $count; $x++) {
		    if (!isset($version_created_list[$x])) {
			$new_version_created = 1;
			$version_created_list[] = date("Y-m-d", time());
		    }
		}
		if (1 == $new_version_created)
		    $row['version_created_list'] = implode(",", $version_created_list);
	    }
	    if ($goal_dates <> $result->version_goal_list)
		$row['version_goal_list'] = $goal_dates;
	}
	if ("yes" == $_POST['delete']) {
	    $row['hidden'] = 1;
	    if ($wpdb->update($bugerator_project_table, $row, array('id' => $project_id)))
		return array('Project deleted.', '');
	    else
		return array('', 'Updated failed.<br>' . $wpdb->print_error());
	}

	if (!isset($row))
	    return array('Nothing to update.', '');

	if ($wpdb->update($bugerator_project_table, $row, array('id' => $project_id)))
	    return array('Update successful.', '');
	else
	    return array('', 'Updated failed.<br>' . $wpdb->print_error());
    }

    /**
     * Exports the database
     * 
     * Exports the contents of the database and the options doing a complete site backup
     * @global type $wpdb
     * @global string $upload_dir
     * @global string $bugerator_issue_table
     * @global string $bugerator_notes_table
     * @global string $bugerator_project_table
     * @return string
     */
    function admin_db_export() {
	//make sure we can do this
	if (false == $this->is_admin())
	    return $this->show_project();
	// Get all the tables and the dir
	global $wpdb;
	global $upload_dir;
	global $bugerator_issue_table;
	global $bugerator_notes_table;
	global $bugerator_project_table;
	$error_msg = "";
	$message = "";
	// we'll add to included_attachments if anything is there.
	$included_attachments = array();


	// show the form confirming and asking if we want to include attachment files
	if (!isset($_POST['bugerator_export'])) {
	    $output = '
	    <form enctype="multipart/form-data" name="export_db" method="post" action="">
		    <input type="hidden" name="bugerator_export" value="yes">
		    ' . wp_nonce_field('bugerator_export', 'export_nonce') . '
		    This will export all of your bugerator options, projects, and posts.  In
		    addition you can choose to include the attached files which may increase the
		    size of the download file.  Do you wish to include attachment files?<br/>
		    <input type="radio" name="include_attach" value="yes" >Yes<br/>
		    <input type="radio" name="include_attach" value="no" >No<br/>
		    <input type="submit" name="Submit" class="button-primary" >
		    ';
	    return $output;
	}
	if (false == wp_verify_nonce($_POST['export_nonce'], 'bugerator_export')) {
	    return $this->choose_project();
	}

	// if we don't want attachments then skip the grouping process.
	if ("no" == $_POST['include_attach']) {

	    goto process_files;
	}

	// get a list of all the files from the two tables
	$sql = "SELECT filename FROM $bugerator_issue_table WHERE filename <> 0";
	$file_array = $wpdb->get_results($sql, ARRAY_N);
	$sql = "SELECT filename FROM $bugerator_notes_table WHERE filename <> 0";
	$results = $wpdb->get_results($sql, ARRAY_N);

	// either one of these can be an array or neither might be so make sure we don't thow a notice.
	if (is_array($file_array)) {
	    // yes $file_array is an array
	    if (is_array($results)) {
		// both are arrays so combine them
		$file_array = array_merge($file_array, $results);
	    } // if file_array is and results isn't then we don't need to do more
	} else {
	    // $file_array is not an array. $results may or may not be but the outcome is the same
	    $file_array = $results;
	}

	// now that we've combined them if this is an array we have files
	foreach ($file_array as $file) {
	    $included_attachments[] = $file[0];
	}

	process_files:
	// the file we will use to list the attachments
	$file_list = "";

	// included attachments either has files or it doesn't but a foreach works regardless
	foreach ($included_attachments as $file) {
	    // simple string list we'll include as a file.
	    $file_list .= "$file\r\n";
	}
	$fp = fopen("$upload_dir/filelist.txt", "w");
	fwrite($fp, $file_list);
	fclose($fp);

	// Open and create a csv file for all of the tables.
	$tables = array(
	    'bugerator_issues.csv' => $bugerator_issue_table,
	    'bugerator_notes.csv' => $bugerator_notes_table,
	    'bugerator_projects.csv' => $bugerator_project_table
	);
	foreach ($tables as $file_name => $table_name) {
	    // start with headers
	    $sql = "DESCRIBE $table_name";
	    $results = $wpdb->get_results($sql, ARRAY_A);
	    $file_output = "";
	    foreach ($results as $result) {
		$file_output .= $result['Field'] . ",";
	    }
	    // cut off ending comma and add carriage return
	    $file_output = substr($file_output, 0, -1) . "\r\n";
	    // Now get the contents
	    $sql = "SELECT * FROM $table_name";
	    $results = $wpdb->get_results($sql, ARRAY_N);
	    foreach ($results as $result) {
		for ($x = 0; $x < count($result); $x++) {
		    $file_output .= '"' . $result[$x] . '",';
		}
		$file_output = substr($file_output, 0, -1) . "\r\n";
	    }

	    $fp = fopen($upload_dir . "/" . $file_name, "w");
	    if ($false === fp) {
		$error_msg .= "Unable to create file $file_name.<br.>";
		continue;
	    }
	    if (false === fwrite($fp, $file_output)) {
		$error_msg .= "Unable to write file $file_name.<br.>";
		fclose($fp);
		continue;
	    }
	    fclose($fp);
	    $message .= "$file_name written successfully.<br/>";
	}
	// get all of the options in the option table. I should use get_option but this will be faster and easier
	$sql = "Select option_name, option_value FROM " . $wpdb->prefix . "options WHERE option_name LIKE 'bugerator_%'";
	$results = $wpdb->get_results($sql, ARRAY_N);
	$file_output = "option_name,option_value\r\n";

	foreach ($results as $result) {
	    $file_output .= '"' . $result[0] . '","' . $result[1] . "\"\r\n";
	}
	$fp = fopen($upload_dir . "/bugerator_options.csv", "w");
	if ($fp === false) {
	    $error_msg .= "Unable to create file bugerator_options.csv.<br/>";
	    continue;
	}
	if (false === fwrite($fp, $file_output)) {
	    $error_msg .= "Unable to write file bugerator_options.csv.<br/>";
	    fclose($fp);
	    continue;
	} else {
	    $message .= "bugerator_options.csv written successfully.<br/>";
	}
	fclose($fp);
	// user mapping - we're going to get all of the IDs and associated names
	$all_users = array();

	// quickie helper function to start building the users
	function return_users($table, $field) {
	    global $wpdb;
	    $sql = "SELECT $field FROM $table group by $field";
	    $results = $wpdb->get_results($sql, ARRAY_N);
	    $users = array();
	    foreach ($results as $result) {
		$users[] = $result['0'];
	    }
	    return $users;
	}

	// start with issues table
	$user_array = return_users($bugerator_issue_table, "submitter");
	if (is_array($user_array))
	    $all_users = array_merge($all_users, $user_array);
	$user_array = return_users($bugerator_issue_table, "assigned");
	if (is_array($user_array))
	    $all_users = array_merge($all_users, $user_array);
	$user_array = return_users($bugerator_issue_table, "subscribers");
	// subscribers are comma dilimeted
	foreach ($user_array as $subscribers) {
	    $new_array = explode(",", $subscribers);
	    if (is_array($new_array))
		$all_users = array_merge($all_users, $new_array);
	}
	// now the notes table
	$user_array = return_users($bugerator_notes_table, "user");
	if (is_array($user_array))
	    $all_users = array_merge($all_users, $user_array);
	// now the projects table
	$user_array = return_users($bugerator_project_table, "owner");
	if (is_array($user_array))
	    $all_users = array_merge($all_users, $user_array);
	$user_array = return_users($bugerator_project_table, "admins");
	// subscribers are comma dilimeted
	foreach ($user_array as $subscribers) {
	    $new_array = explode(",", $subscribers);
	    if (is_array($new_array))
		$all_users = array_merge($all_users, $new_array);
	}
	$user_array = return_users($bugerator_project_table, "developers");
	// subscribers are comma dilimeted
	foreach ($user_array as $subscribers) {
	    $new_array = explode(",", $subscribers);
	    if (is_array($new_array))
		$all_users = array_merge($all_users, $new_array);
	}
	$user_array = return_users($bugerator_project_table, "subscribers");
	// subscribers are comma dilimeted
	foreach ($user_array as $subscribers) {
	    $new_array = explode(",", $subscribers);
	    if (is_array($new_array))
		$all_users = array_merge($all_users, $new_array);
	}
	// finally the options.  Whew
	$user_array = explode(",", get_option('bugerator_admins'));
	if (is_array($user_array))
	    $all_users = array_merge($all_users, $user_array);
	$user_array = explode(",", get_option('bugerator_developers'));
	if (is_array($user_array))
	    $all_users = array_merge($all_users, $user_array);

	// take out duplicates
	$all_users = array_unique($all_users);

	// finally get the usernames and format it for a file;
	$userfile_output = "user_id,display_name\r\n";
	foreach ($all_users as $user) {
	    $user_info = get_userdata($user);
	    if (!$user_info)
		continue;
	    $userfile_output .= "$user,\"" . $user_info->display_name . "\"\r\n";
	}

	$fp = fopen($upload_dir . "/bugerator_users.csv", "w");
	if ($fp === false) {
	    $error_msg .= "Unable to create file bugerator_users.csv.<br/>";
	    continue;
	}
	if (false === fwrite($fp, $userfile_output)) {
	    $error_msg .= "Unable to write file bugerator_users.csv.<br/>";
	    fclose($fp);
	    continue;
	} else {
	    $message .= "bugerator_users.csv written successfully.<br/>";
	}
	fclose($fp);


	// time to zip it and prepare it for download
	$files_to_zip = array_keys($tables);
	$files_to_zip[] = "bugerator_options.csv";
	$files_to_zip[] = "bugerator_users.csv";
	$files_to_zip[] = "filelist.txt";
	$zip = new ZipArchive;
	if (true === $zip->open("$upload_dir/export.zip", ZipArchive::CREATE)) {
	    foreach ($files_to_zip as $file) {
		$zip->addFile("$upload_dir/$file", $file);
	    }
	    foreach ($included_attachments as $file) {
		$zip->addFile("$upload_dir/$file", $file);
	    }
	    $zip->close();
	    $message .= "Zip file created. <br/>";
	    $dir = wp_upload_dir();

	    $main_msg = "Process completed successfully.<br/>";
	    $main_msg .= "<a href='" . $dir['baseurl'] . "/bugerator/export.zip'>Click to download export file.</a>";
	    foreach ($files_to_zip as $file) {
		unlink("$upload_dir/$file");
	    }
	} else {
	    $error_msg .= "Unable to create zip file.<br/>" . $zip->getStatusString();
	}


	return array($main_msg, $error_msg, $message);
    }

    /**
     * Restores the project
     * 
     * Upload a zip file, check it for a db, then override the existing database with the zip
     * 
     * @global type $wpdb
     * @global string $upload_dir
     * @global string $bugerator_issue_table
     * @global string $bugerator_notes_table
     * @global string $bugerator_project_table
     * @return string
     */
    function admin_db_import() {
	//make sure we can do this
	if (false === $this->is_admin())
	    return $this->show_project();
	global $wpdb;
	global $upload_dir;
	global $bugerator_issue_table;
	global $bugerator_notes_table;
	global $bugerator_project_table;
	$output = "";
	$error_msg = "";
	$message = "";
	$status_msg = "";

	// Open and create a csv file for all of the tables.
	$tables = array(
	    'bugerator_issues.csv' => $bugerator_issue_table,
	    'bugerator_notes.csv' => $bugerator_notes_table,
	    'bugerator_projects.csv' => $bugerator_project_table
	);
	$files = array_keys($tables);
	$files[] = "bugerator_options.csv";
	$files[] = "bugerator_users.csv";
	$files[] = "filelist.txt";


	if (isset($_POST['import_nonce']) and
		wp_verify_nonce($_POST['import_nonce'], 'bugerator_addusers')) {
	    goto process_users;
	}


	// if it isn't posted or it fails the nonce then show the form
	if (isset($_POST['admin_import_file']) and
		false !== wp_verify_nonce($_POST['import_nonce'], 'bugerator_import')) {
	    // lets get the file.

	    if (0 == $_FILES['import_file']['error']) { // successfull file load
		// hurray we have a file.
		// Make sure it is a zip
		$filename = $_FILES['import_file']['name'];
		$path_parts = pathinfo($filename);
		$extension = $path_parts['extension'];
		if ($extension != "zip") {
		    $error_msg = "Invalid file type. Must be a valid .zip file.";
		    return array('', $error_msg);
		}

		if (!move_uploaded_file($_FILES['import_file']['tmp_name'], $upload_dir . "/" . "import.zip")) {
		    $error_msg = "Unknown file error. Have the administrator make sure $upload_dir exists and is writable";
		    unlink($upload_dir . "/import.zip"); // Just make sure nothing is there
		    return array("", $error_msg);
		}

		// using the files so only what we expect will be there
		$zip = new ZipArchive;
		if (true === $zip->open("$upload_dir/import.zip")) {
		    $zip->extractTo("$upload_dir/", $files);
		    $zip->close();
		}
		// lets get the attachments out.
		$fp = fopen("$upload_dir/filelist.txt", 'r');
		$attached_files = array();
		while (false !== ($file = fgetcsv($fp))) {
		    $attached_files[] = $file['0'];
		}
		if (true === $zip->open("$upload_dir/import.zip")) {
		    $zip->extractTo("$upload_dir/", $attached_files);
		    $zip->close();
		}
		// that was easy... ;-)
		// Now we need to do user matching so we know what users in the
		// new system match the old.  WP controlls user numbers so we just play
		// along
		$fp = fopen("$upload_dir/bugerator_users.csv", 'r');
		$header = fgetcsv($fp);
		$comparison = array_diff($header, array("user_id", "display_name"));
		if (0 !== count($comparison)) {
		    // the header doesn't match what we expect.
		    return array('', "bugerator_users.csv is invalid.<br>Try exporting again.");
		}
		$users = array();
		while (false !== ($row = fgetcsv($fp))) {
		    $users[$row[0]] = $row[1];
		}


		// Get a list of all users
		$sql = "SELECT ID, display_name FROM " . $wpdb->users . " ORDER BY display_name";
		$results = $wpdb->get_results($sql, ARRAY_N);
		$big_user_list = array();
		foreach ($results as $result) {
		    $big_user_list[$result[0]] = $result[1];
		}
		// mapping the old keys to the new based on the display names.
		// this will be user configurable in a form.
		$user_map = array();

		foreach ($users as $key => $name) {
		    $user_map[$key] = array_search($users[$key], $big_user_list);
		}

		$output .= "<form method=post action='' >\r\n" .
			wp_nonce_field('bugerator_addusers', 'import_nonce') . "<br/>\r\n";
		$output .= "<table class=user_match>\r\n\t<tr class=user_match>\r\n\t\t" .
			"<td class=user_match colspan=2 ><p>Please match up the old users with the new ones. " .
			"In some cases based on the names we may have found a match.</td>" .
			"\r\n\t</tr>\r\n\t<tr class=user_match >\r\n";
		foreach ($user_map as $ourkey => $wpkey) {
		    $output .= "\r\n\t\t<td class='form_left'>$users[$ourkey]</td>" .
			    "\r\n\t\t<td class='form_right'><select name=user_map[$ourkey]>\r\n";

		    foreach ($big_user_list as $key => $name) {
			$output .= "\t<option value='$key' ";
			if ($key == $wpkey)
			    $output .= "SELECTED ";
			$output .= ">$name\r\n";
		    }
		    $output .= "\r\n\t\t</select>\r\n\t\t</td>\r\n\t</tr>";
		}
		$output .= "<tr><td colspan='2'><input type=submit value=Submit class='button-primary' >
		    </td></tr></table></form>";
		return array('', '', $output);

		// we now have a conversion from one set of users to the other
		process_users:
		$user_map = $_POST['user_map'];


		// files are there. now to process.
		foreach ($tables as $filename => $table) {
		    $fp = fopen("$upload_dir/$filename", "r");

		    // the purpose here is be ready to delete users and subscribers if
		    // the WP user database is different.
		    if ($table == $bugerator_issue_table) {
			$users = array(11, 12);
			$csv_fields = array(13);
			$header_check = array("id", "project_id", "title", "type", "description",
			    "filename", "status", "priority", "version", "submitted", "updated",
			    "submitter", "assigned", "subscribers", "hidden");
		    } elseif ($table == $bugerator_notes_table) {
			$users = array(4);
			$csv_fields = array();
			$header_check = array("id", "issue_id", "notes", "filename",
			    "user", "time", "hidden");
		    } else {
			$users = array(2);
			$csv_fields = array(6, 7, 12);
			$header_check = array("id", "name", "owner", "current_version", "version_date",
			    "status", "admins", "developers", "version_list", "version_created_list",
			    "version_goal_list", "hidden", "subscribers");
		    }
		    $headers = fgetcsv($fp);

		    $comparison = array_diff($headers, $header_check);
		    if (0 !== count($comparison)) {
			// the header doesn't match what we expect.
			return array('', "$filename is invalid.<br>Try exporting again.");
		    }
		    $file_contents = array();

		    // get the csv file in an array.  Fix the users row by row
		    while (false !== ( $row = fgetcsv($fp))) {
			foreach ($users as $key) {
			    // here row[key] is the old vallue and $user_map[oldvalue] = new value
			    if ("" == $row[$key] or 0 == $row[$key])
				continue;
			    $row[$key] = $user_map[$row[$key]];
			}
			foreach ($csv_fields as $key) {
			    $newline = array();
			    // this outputs the comma dilited line from the db
			    $line = explode(",", $row[$key]);
			    // iterate and make a new array with converted values
			    foreach ($line as $old_key) {
				// it ain't always populated
				if ("" == $old_key)
				    continue;
				$newline[] = $user_map[$old_key];
			    }
			    // combine the new values to insert in to the row
			    if (!is_array($newline))
				continue;
			    $new_row = implode(",", $newline);
			    $row[$key] = $new_row;
			}
			$file_contents[] = $row;
		    }
		    // kill the table
		    $sql = "DELETE FROM $table WHERE 1=1";
		    $wpdb->query($sql);
		    // iterate through the file contents and insert each row
		    foreach ($file_contents as $row) {
			$insert = array();
			for ($x = 0; $x < count($headers); $x++) {
			    $insert[$headers[$x]] = $row[$x];
			}
			$wpdb->insert($table, $insert);
		    }
		}

		// Time to import the options.
		$fp = fopen("$upload_dir/bugerator_options.csv", "r");
		$header = fgetcsv($fp);
		// just iterate and update them all.
		while (false !== ($row = fgetcsv($fp))) {
		    update_option($row['0'], $row['1']);
		}
		fclose($fp);

		// todo: deal with the attached files;
		// kill the csv files
		foreach ($files as $file) {
		    unlink("$upload_dir/$file");
		}
		// kill the import zip file
		unlink("$upload_dir/import.zip");
		return array('Process completed successfully.', '');
	    }
	} else {
	    // file upload form
	    $output .= '
		<form enctype="multipart/form-data" name="import_db" method="post" action="">
		    <input type="hidden" name="admin_import_file" value="yes">
		    ' . wp_nonce_field('bugerator_import', 'import_nonce') . '
		    Please choose your valid import file.  It must have been exported
		    from bugerator.<br/>
		    <input type="file" name="import_file" class="button-primary" ><br/>
		    <input type="submit" name="Submit" class="button-primary" >
		    ';
	    return $output;
	}
    }

    /**
     * checks if the current user is an admin and returns true or false
     * 
     * checks if the current user is an admin and returns true or false
     * @global type $user
     * @global string $bugerator_project_table
     * @global type $wpdb
     * @param type $project_id
     * @return boolean
     */
    function is_admin($project_id = -1) {
	global $user;
	global $bugerator_project_table;
	global $wpdb;
	if (0 == $user->ID)
	    return false;
	$admins = explode(",", get_option('bugerator_aditional_admins'));
	if ($project_id > 0) {
	    $sql = "SELECT admins from $bugerator_project_table where id = '$project_id'";
	    $project_admins = explode(",", $wpdb->get_var($sql));
	}
	$admin = true;
	if (false == array_search($user->ID, $admins) and
		(false == current_user_can('manage_options')))
	    $admin = false;

	if ($project_id > 0 and false !== array_search($user->ID, $project_admins)) {
	    $admin = true;
	}
	return $admin;
    }

    /**
     * Returns the options
     * 
     * returns an array of the user selectible options
     * @return type
     */
    function get_options() {
	$options_array = explode(",", get_option('bugerator_options'));
	for ($x = 0; $x < count($options_array); $x++) {
	    $this_option = explode("|", $options_array[$x]);
	    $options[$this_option[0]] = $this_option[1];
	}
	return $options;
    }

    /**
     * Returns the options for a specific project
     * 
     * Returns the options for a specific project
     * 
     * @global type $wpdb
     * @global string $bugerator_project_table
     * @param type $project_id
     * @return type
     */
    function get_project_options($project_id) {
	global $wpdb;
	global $bugerator_project_table;
	$sql = "SELECT options FROM $bugerator_project_table WHERE id = '$project_id'";
	$project_options = $wpdb->get_var($sql);
	$options_array = explode(",", $project_options);
	if ("" == $options_array[0])
	    return array();
	for ($x = 0; $x < count($options_array); $x++) {
	    $this_option = explode("|", $options_array[$x]);
	    $options[$this_option[0]] = $this_option[1];
	}
	return $options;
    }

    /**
     * Adds a user to the subscription list for the specified issue
     * 
     * This takes in an issue id and will add the current user as a subscriber
     * to that issue.
     * 
     * @global type $wpdb
     * @global type $user
     * @global string $bugerator_subscriptions
     * @param type $issue_id 
     */
    function add_subscription($issue_id, $project_id = -1) {
	global $wpdb;
	global $user;
	global $bugerator_subscriptions;

	// first make sure we aren't subscribed to the project
	$sql = "SELECT count(id) FROM $bugerator_subscriptions WHERE type='Projects' and " .
		"user = '$user->ID' and foreign_id = '$project_id'";
	$project_sub = $wpdb->get_var($sql);

	if (1 == $project_sub)
	    return;

	$sql = "SELECT count(id) FROM $bugerator_subscriptions WHERE foreign_id = '$issue_id' and " .
		"user = '$user->ID'";
	$result = $wpdb->get_var($sql);

	if (0 == $result) {
	    // Yay somebody is subscribing.
	    $data = array(
		"user" => $user->ID,
		"type" => "Issues",
		"foreign_id" => $issue_id,
		"visited" => 1
	    );
	    $wpdb->insert($bugerator_subscriptions, $data);
	}
	$output = "<h3 class='bugerator'>Subscription added.</h3>\r\n";
	return $output;
    }

    /**
     * Checks if the current user is subscribed to an issue. 
     * 
     * Checks if the current user is subscribed to an issue. Returns false if they are not subscribed and
     * returns true if they are subscribed or if there is no user logged in (so it won't display the subscribe
     * link)
     * 
     * @global type $wpdb
     * @global type $user
     * @global string $bugerator_subscriptions
     * @param type $issue_id
     * @return boolean 
     */
    function check_subscription($issue_id) {
	global $wpdb;
	global $user;
	global $bugerator_subscriptions;

	if (false == is_user_logged_in())
	    return true;
	$sql = "SELECT count(id) FROM $bugerator_subscriptions WHERE foreign_id = '$issue_id' AND " .
		"user = '$user->ID'";
	$result = $wpdb->get_var($sql);

	if (0 == $result) {
	    return false;
	}
	return true;
    }

    /**
     * email subscribers when something is updated
     * 
     * Goes through the subscriber list.  Eliminates duplicates (people subscribed to a fll project and an issue,
     * prepares the email, and markes the database so we know when they have logged in again so another email is 
     * generated.
     * @global type $project_id
     * @global type $wpdb
     * @global type $post
     * @global string $bugerator_subscriptions
     * @param type $issue_id
     * @param type $title
     * @param type $project
     * @param type $change 
     */
    function email_subscribers($issue_id, $title, $project_name, $change, $bulk = false) {
	global $project_id;
	global $wpdb;
	global $post;
	global $bugerator_subscriptions;
	global $bugerator_issue_table;
	global $user;
	$emails = array();

	// set up the final line of the email so it is edited in the same place.
	$closing_line = "To update your subscription preferences go to $post->guid, log in to your Wordpress " .
		"account, and click the options tab inside the Bugerator app.";

	// first get a list of everybody who wants an email update.
	$sql = "SELECT user,visited FROM $bugerator_subscriptions WHERE type = 'Projects' and foreign_id = '$project_id'";
	$project_subscriber = $wpdb->get_results($sql);
	$want_all_emails = explode(",", get_option('bugerator_subscribers_all_email'));

	// bulk updates have different parameters.
	if ($bulk === true)
	    goto bulk_update;

	$sql = "SELECT user,visited FROM $bugerator_subscriptions WHERE type = 'Issues' and foreign_id = '$issue_id'";
	$issue_subscriber = $wpdb->get_results($sql);

	foreach ($project_subscriber as $subscriber) {
	    // simplify the array for search
	    $project_person[] = $subscriber->user;

	    // Subject will be unique to project or issue subscribes. Message will be the same
	    $array['user'] = $subscriber->user;
	    $array['subject'] = "$project_name update: Change in issue #$issue_id.";
	    $array['visited'] = $subscriber->visited;
	    $array['message'] = "You are receiving this because you subscribed to all updates from $project_name." .
		    "  Issue # $issue_id: \"$title\" has been updated by $user->display_name.\r\n" .
		    "The following changes have been made:\r\n\r\n" .
		    "$change\r\n\r\n" . $closing_line;


	    $emails[] = $array;
	}

	foreach ($issue_subscriber as $subscriber) {
	    if (false === array_search($subscriber->user, $project_person)) {
		$array['user'] = $subscriber->user;
		$array['visited'] = $subscriber->visited;
		$array['subject'] = "$project_name update: Change in issue #$issue_id.";
		$array['message'] = "You are receiving this because you subscribed to all updates from issue # " .
			"$issue_id: \"$title.\"  This issue has been updated by $user->display_name.\r\n" .
			"The following changes have been made:\r\n\r\n" .
			"$change\r\n\r\n" . $closing_line;

		$emails[] = $array;
	    }
	}
	goto send_emails;

	// the bulk update will follow a different pattern.  The $issue_id
	// will be an array.  Message will be the same.
	bulk_update:
	//$issue_id, $title, $project_name, $change, $bulk = false 
	$selections_to_edit = $issue_id;
	if (0 == count($selections_to_edit))
	    return;
	$selections_to_edit_sql = "( \"" . implode('","', $selections_to_edit) . "\" )";

	$sql = "SELECT id, title from $bugerator_issue_table " .
		"WHERE id IN $selections_to_edit_sql";
	$results = $wpdb->get_results($sql);

	$sql = "SELECT user,visited FROM $bugerator_subscriptions WHERE type = 'Projects' and foreign_id = '$project_id'";
	$project_subscriber = $wpdb->get_results($sql);

	// start the project subscriber message
	$subject = "$project_name updates: Issues edited";
	$message = "You are receiving this because you subscribed to all updates from $project_name." .
		"  The following issues have been edited by $user->display_name.\r\n";

	for ($x = 0; $x < count($issue_id); $x++) {
	    $sql = "SELECT title FROM $bugerator_issue_table WHERE id = '" . $issue_id[$x] . "'";
	    $title = $wpdb->get_var($sql);
	    $message .= "Issue #$issue_id[$x] $title\r\n$change[$x]\r\n\r\n";
	    $array['subject'] = "$project_name update: Change in issue #$issue_id[$x].";
	    $array['message'] = "You are receiving this because you subscribed to all updates from issue # " .
		    "$issue_id[$x]: \"$title.\"  This issue has been updated by $user->display_name.\r\n" .
		    "The following changes have been made:\r\n\r\n" .
		    "$change[$x]\r\n\r\n" . $closing_line;
	    $array['issue_id'] = $issue_id[$x];
	    $single_emails[] = $array;
	}
	$message .= $closing_line;
	// we have the message for the project subscribers.  now create the email
	foreach ($project_subscriber as $subscriber) {
	    // simplify the array for search
	    $project_person[] = $subscriber->user;

	    // Subject will be unique to project or issue subscribes. Message will be the same
	    $array['user'] = $subscriber->user;
	    $array['subject'] = $subject;
	    $array['visited'] = $subscriber->visited;
	    $array['message'] = $message;

	    $emails[] = $array;
	}

	// now iterate through the issues and set up the issue sibscruber emails
	foreach ($single_emails as $issues) {
	    $issue_id = $issues['issue_id'];
	    $sql = "SELECT user,visited FROM $bugerator_subscriptions WHERE type = 'Issues' and foreign_id = '$issue_id'";
	    $issue_subscriber = $wpdb->get_results($sql);
	    foreach ($issue_subscriber as $subscriber) {
		if (false === array_search($subscriber->user, $project_person)) {
		    $array['user'] = $subscriber->user;
		    $array['visited'] = $subscriber->visited;
		    $array['subject'] = $issues['subject'];
		    $array['message'] = $issues['message'];

		    $emails[] = $array;
		}
	    }
	}


	send_emails:
	foreach ($emails as $message) {
	    if (1 == $message['visited']) {
		if (false === array_search($message['user'], $want_all_emails)) {
		    // They have visited since the last email so we are ok sending them another
		    if ($user->ID != $message['user']) { // don't send email if they are the one changing it
			$email_to_send[] = $message;
			$sql = "UPDATE $bugerator_subscriptions SET visited = '0' WHERE " .
				"user = '" . $message['user'] . "'";
			$wpdb->query($sql);
		    }
		} else {
		    // They want updates whether they have visited or not.
		    $email_to_send[] = $message;
		}
	    } elseif (0 == $message['visited'] and true === array_search($message['user'], $want_all_emails)) {
		// These also now want an update no matter what.
		$email_to_send[] = $message;
	    }
	}
	if (!isset($email_to_send))
	    return true;
	foreach ($email_to_send as $mail) {
	    $to = get_userdata($mail['user']);
	    $name = $to->data->user_nicename;
	    if (strstr($to->data->user_email, "<") === false)
		$name .= "<" . $to->data->user_email . ">";
	    else
		$name .= $to->data->user_email;
	    if (false === wp_mail($name, $mail['subject'], $mail['message'])) {
		echo "Email failed: $name<br/>\r\n";
	    }
	}
	return true;
    }

}

/* * **************************
 * Menu Class
 * Does the options menu and all related whatevers
 * This is inside the admin section of WP
 */

class BugeratorMenu {

    /**
     * This is called by the add_action bugerator menu. Just generates a menu
     */
    function show_menu() {
	add_options_page('Bugerator Options', 'Bugerator', 'manage_options', 'bugerator_menu', array('BugeratorMenu', 'options_page'));
    }

    /**
     * Shows the options page on the admin screen.
     */
    function options_page() {
	global $path;
	//must check that the user has the required capability
	if (!current_user_can('manage_options')) {
	    wp_die(__('You do not have sufficient permissions to access this page.'));
	}

	$user = wp_get_current_user();
	$user_id = $user->ID;
	$admins = get_option('bugerator_admins');
	if ("" == $admins)
	    update_option('bugerator_admins', $user_id);
	elseif (false === strstr($admins, ",") and $admins <> $user_id)
	    update_option('bugerator_admins', "$admins,$user_id");
	else {
	    $admins = explode(",", $admins);
	    if (false === in_array($user_id, $admins)) {
		$admins[] = $user_id;
		$admins = implode(",", $admins);
		update_option('bugerator_admins', $admins);
	    }
	}


	// first see if we've had something submitted
	BugeratorMenu::check_for_color_changes();
	global $path;


	$tabs = array(
	    "global" => "Global options",
	    "users" => "Edit global admins/devs",
	    "change_css" => "CSS/Display changes"
	);
	$subtabs = BugeratorMenu::get_subtabs();

	if (isset($_GET['tab']) and $_GET['tab'] != "reset_css")
	    $navigation = $_GET['tab'];
	else
	    $navigation = "global";

	if (isset($_GET['subtab']) and $_GET['subtab'] != "reset_css")
	    $subnavigation = $_GET['subtab'];
	else
	    $subnavigation = "all";



	switch ($navigation) {
	    case "global":
		$content = BugeratorMenu::bugerator_option_general();
		break;
	    case "change_css":
		$content = BugeratorMenu::bugerator_option_css($subnavigation);
		break;
	    case "users":
		$content = BugeratorMenu::bugerator_edit_users();
		break;
	}



	include($path . "/bugerator_options_page_tpl.php");
    }

    /**
     * This creates / edits global developers and admins.
     * 
     * This creates / edits global developers and admins.  This can be done
     * individualy by project as well.
     * @global type $wpdb
     * @global type $path
     * @return type
     */
    function bugerator_edit_users() {
	global $wpdb;
	global $path;
	$nonce = wp_create_nonce('bugerator_users');

	// get and format all users on the system.
	$sql = "SELECT ID,display_name from " . $wpdb->users;
	$name_array = $wpdb->get_results($sql);
	foreach ($name_array as $name) {
	    $display_names[$name->ID] = $name->display_name;
	}

	$admins = explode(",", get_option('bugerator_admins'));
	$developers = explode(",", get_option('bugerator_developers'));
	$admin_names = "";
	$developer_names = "";
	$user = wp_get_current_user();
	$user_id = $user->ID;
	if ($admins[0] <> "") {
	    foreach ($admins as $key) {
		$admin_names .= $display_names[$key];
		if ($key <> $user_id)
		    $admin_names .= " <span id='rm_admin_$key' >
		    <a onClick='remove_user(\"admins\",\"$key\")'>Click to remove admin.</a></span> ";
		$admin_names .= " <br/>\r\n";
	    }
	} else {
	    $admin_name = "There are no global admins.\r\n";
	}
	// now developer list
	if ($developers[0] <> "") {
	    foreach ($developers as $key) {
		$developer_names .= $display_names[$key] .
			" <span id='rm_developer_$key' ><a onClick='remove_user(\"developers\",\"$key\")'>
			Click to remove developer.</a></span><br/>\r\n";
	    }
	} else {
	    $developer_names = "There are no global developers.\r\n";
	}
	ob_start();
	include("$path/bugerator_options_users_tpl.php");
	$output = ob_get_clean();
	return $output;
    }

    /**
     * Displays css options for editing
     * 
     * This parses the bugerator.css file and returns an array with the different css
     * values for the different pages.
     * @global type $path
     * @return type
     */
    function bugerator_get_css() {
	global $path;
	// get the style.
	$fp = fopen($path . "/bugerator.css", "r");
	$css_page = fread($fp, filesize("$path/bugerator.css"));
	fclose($fp);


	// Page is seperated with "#seperator{}" tags
	$css_array = explode("#seperator{}", $css_page);

	$output_css = array();
	$x = 0;
	// now parse the comments for the sections and create an associative array
	foreach ($css_array as $page_css) {
	    $x++;
	    // first one is a throwaway
	    if (1 == $x)
		continue;
	    $position = strpos($page_css, "*/");

	    $key = trim(substr($page_css, 4, $position - 4));
	    $value = trim(substr($page_css, $position + 2));
	    $output_css[$key] = $value;
	    // easy as pie
	}
	return $output_css;
    }

    /**
     * Takes in css and writes a new file
     * 
     * This parses the a css array from bugerator_get_css and creates the bugerator.css
     * 
     * @global type $path
     * @param type $input_css
     * @return boolean
     */
    function bugerator_put_css($input_css) {
	global $path;

	$output = "/* Note: feel free to edit but if you take out the #seperator tags or change the
heading names (ie. bugerator_css_all) then the program won't be able to parse this. */\r\n";
	foreach ($input_css as $key => $page_css) {
	    $output .= "#seperator{}\r\n";
	    $output .= "/* $key */\r\n";
	    $output .= $page_css;
	}


	// write the style.
	$fp = fopen($path . "/bugerator.css", "w");
	if (false === fwrite($fp, $output))
	    echo "Error creating bugerator.css";
	fclose($fp);

	return true;
    }

    /**
     * Changes colors for the issue types - new, assigned, etc.
     * 
     * 
     */
    function check_for_color_changes() {
	// see if the color form posted
	if (isset($_POST['bugerator_color_form']) and "yup" == $_POST['bugerator_color_form']
		and wp_verify_nonce($_POST['bugerator_options_nonce'], 'bugerator_options')) {
	    $color_choices = implode(",", $_POST['color_choice']);
	    $color_choices = sanitize_text_field($color_choices);
	    $text_color_choices = implode(",", $_POST['text_color_choice']);
	    $text_color_choices = sanitize_text_field($text_color_choices);
	    update_option('bugerator_status_colors', $color_choices);
	    update_option('bugerator_status_text_colors', $text_color_choices);
	}

	// see if we want to reset the colors to default
	if (isset($_POST['bugerator_reset_colors']) and 'yes please' == $_POST['bugerator_reset_colors']
		and wp_verify_nonce($_POST['bugerator_options_nonce'], 'bugerator_options')) {
	    $bugerator_install = new BugeratorInstall;

	    $default_colors = BugeratorInstall::get_default_colors();

	    // get the default colors & the existing colors and turn them in to arrays
	    $default_color_array = explode(",", $default_colors[0]);
	    $default_text_color_array = explode(",", $default_colors[1]);
	    unset($bugerator_install);
	    $present_colors = explode(",", get_option('bugerator_status_colors'));
	    $present_text_colors = explode(",", get_option('bugerator_status_text_colors'));
	    //Override the present colors with the default only for the default categories.
	    for ($x = 0; $x < count($default_color_array); $x++) {
		$present_colors[$x] = $default_color_array[$x];
		$present_text_colors[$x] = $default_text_color_array[$x];
	    }
	    // put it back as a string.
	    $new_colors = implode(",", $present_colors);
	    $new_text_colors = implode(",", $present_text_colors);
	    update_option('bugerator_status_colors', $new_colors);
	    update_option('bugerator_status_text_colors', $new_text_colors);
	}
    }

    /**
     * we are accessing the subtabs from multiple places so I'm putting it in a function
     * 
     * @return string
     */
    function get_subtabs() {
	$subtabs = array(
	    "all" => "System wide css",
	    "choose" => "Choose project",
	    "status_colors" => "Status colors",
	    "issue_list_css" => "Issue list",
	    "issue_detail_css" => "Issue detail",
	    "version_map" => "Version Map",
	    "add_issue_css" => "New issue"
	);
	return $subtabs;
    }

    /**
     * Shows the different css pages and navigates between them
     * @param type $subtab
     * @return type
     */
    function bugerator_option_css($subtab) {
	$css_array = BugeratorMenu::bugerator_get_css();
	switch ($subtab) {
	    case "all":
		return BugeratorMenu::bugerator_option_css_all($css_array);
		break;
	    case "issue_list_css":
		return BugeratorMenu::bugerator_option_css_issue_list($css_array);
		break;
	    case "issue_detail_css":
		return BugeratorMenu::bugerator_option_css_issue_detail($css_array);
		break;
	    case "status_colors":
		return BugeratorMenu::bugerator_option_css_status_colors($css_array);
		break;
	    case "add_issue_css":
		return BugeratorMenu::bugerator_option_css_add_issue($css_array);
		break;
	    case "choose":
		return BugeratorMenu::bugerator_option_css_choose_project($css_array);
		break;
	    case "version_map":
		return BugeratorMenu::bugerator_option_css_version_map($css_array);
		break;

	    default:
		return BugeratorMenu::bugerator_option_css_all($css_array);
		break;
	}
    }

    /**
     * Edit the global css and the general options
     * @global type $path
     * @global type $post
     * @param type $css_array
     * @return type
     */
    function bugerator_option_css_all($css_array) {
	global $path;
	global $post;
	$nonce = wp_create_nonce('bugerator_options');

	$menu = BugeratorMain::get_menu("list", true, "1", "1", "?page=bugerator_menu&tab=change_css&subtab=all");

	$content = "<h1>Test content</h1><br/><br/><br/>Test content";


	$page = "<div style=\"width: 90%; margin: auto;\">";
	if (!isset($post->guid))
	    $page .="<h3>Note: This is goverened by the theme and may not look the same here.</h3>";
	$page .= "<div class=bugerator_page id=bugerator_page >\r\n" .
		$menu . "\r\n<div class=bugerator_content id=bugerator_content >\r\n" .
		$content . "\r\n</div><!-- bugerator_content -->\r\n</div><!-- bugerator_page --></div>";

	$parsed = BugeratorMenu::css_parse($css_array['bugerator_css_all']);
	// bugerator_css_all
	$css_form = "";
	$global_css_form = BugeratorMenu::get_css_change_form($parsed, "bugerator_css_all");

	$css_message = "Just use as a general color guide.";


	ob_start();
	include("$path/bugerator_options_css_edit_tpl.php");
	$content = ob_get_clean();

	return $content;
    }

    /**
     * Edit the general options
     * 
     * Stuff like the date format, if anonymous users can post, etc.
     */
    function bugerator_option_general() {
	global $path;
	global $post;
	global $wpdb;

	if (!isset($post->guid))
	    $page = "?page=bugerator_menu";
	else {
	    $page = $post->guid . "&bugerator_nav=admin&active_tab=options";
	}
	$nonce = wp_create_nonce('bugerator_options');
	$option_array = explode(",", get_option('bugerator_options'));
	foreach ($option_array as $thisone) {
	    $thisoption = explode("|", $thisone);
	    $options[$thisoption[0]] = $thisoption[1];
	}
	$content = "";

	// see if the CSS reset has been clicked
	if (isset($_GET['reset_css']) and "yes" == $_GET['reset_css'] and
		wp_verify_nonce($_GET['reset_nonce'], 'bugerator_options') != false)
	    return "<h1>Are you sure you want to reset the css to the default?</h1>
                <h3>This can not be undone.</h3>
                <a href='$page" .
		    "&tab=global&reset_css=sure&reset_nonce=$nonce' ><input type='button' " .
		    "value='Yes I want to reset the CSS.' class='button-primary' /></a>" .
		    "&nbsp;&nbsp;<a href='$page" .
		    "&tab=global' ><input type='button' value='No I changed my mind.' class='button-primary' /></a>";
	// see if the CSS reset confirmation has been clicked
	if (isset($_GET['reset_css']) and "sure" == $_GET['reset_css'] and
		false !== wp_verify_nonce($_GET['reset_nonce'], 'bugerator_options')) {
	    // if we need to reset the css we'll just copy the default file over the edited one.
	    unlink("$path/bugerator.css");
	    copy("$path/bugerator-default.css", "$path/bugerator.css");
	    $content = "<h2>CSS has been reset.</h2>\r\n";
	}

	// see if the kill comments has been clicked
	if (isset($_GET['kill_comments']) and "yes" == $_GET['kill_comments'] and
		wp_verify_nonce($_GET['kill_nonce'], 'bugerator_options') != false)
	    return "<h1>Are you sure you want to get rid of the comments sections on the bugerator pages?</h1>
                <h3>This can only be undone through the dashboard.</h3>
                <a href='$page" .
		    "&tab=global&kill_comments=sure&kill_nonce=$nonce' ><input type='button' " .
		    "value='Yes I want to get rid of comments.' class='button-primary' /></a>" .
		    "&nbsp;&nbsp;<a href='$page" .
		    "&tab=global' ><input type='button' value='No I changed my mind.' class='button-primary' /></a>";
	// see if the CSS reset confirmation has been clicked
	if (isset($_GET['kill_comments']) and "sure" == $_GET['kill_comments'] and
		wp_verify_nonce($_GET['kill_nonce'], 'bugerator_options') != false) {
	    // best way to kill the comments is turn it off on every post with the [bugerator] short code
	    $sql = "UPDATE $wpdb->posts SET comment_status = 'closed', ping_status = 'closed' WHERE " .
		    "post_content LIKE '%[bugerator%'";
	    if (false === $wpdb->query($sql))
		$content = "Query failed. $sql";
	    else
		$content = "Pages updated. You should no longer have comments at the bottom.";
	}



	// see if the form has been posted
	if (isset($_POST['bugerator_options_general']) and
		false !== wp_verify_nonce($_POST['options_nonce'], 'bugerator_options')) {
	    // k lets process this bad boy.
	    if (isset($_POST['anonymous_post']))
		$options['anonymous_post'] = "true";
	    else
		$options['anonymous_post'] = "false";
	    if (isset($_POST['upload_files']))
		$options['upload_files'] = "true";
	    else
		$options['upload_files'] = "false";
	    $options['date_format'] = $_POST['date_format'];
	    $options['long_date_format'] = $_POST['long_date_format'];
	    $options['margin'] = intval($_POST['margin']);
	    $options['filesize'] = intval($_POST['filesize']);
	    $option_string = "anonymous_post|" . $options['anonymous_post'] . ",upload_files|" .
		    $options['upload_files'] . ",date_format|" . $options['date_format'] .
		    ",long_date_format|" . $options['long_date_format'] . ",margin|" . $options['margin'] .
		    ",filesize|" . $options['filesize'];
	    update_option('bugerator_options', $option_string);
	    $content = "<h2>Options updated.</h2>\r\n";
	}


	ob_start();
	include("$path/bugerator_options_general_tpl.php");
	$content .= ob_get_clean();
	return $content;
    }

    /**
     * Css for the choose project page
     * @global type $path
     * @global type $post
     * @param type $css_array
     * @return type
     */
    function bugerator_option_css_choose_project($css_array) {
	global $path;
	global $post;
	$nonce = wp_create_nonce('bugerator_options');

	if (!isset($post->guid)) {
	    $post = new stdClass();
	    $post->guid = "";
	}
	$results[0] = new stdClass();
	$results[1] = new stdClass();
	$results[2] = new stdClass();



	$general_css = BugeratorMenu::css_parse($css_array['bugerator_css_all']);
	$page_css = BugeratorMenu::css_parse($css_array['bugerator_css_choose_project']);

	$global_css_form = BugeratorMenu::get_css_change_form($general_css, "bugerator_css_all");
	$css_form = BugeratorMenu::get_css_change_form($page_css, "bugerator_css_choose_project");
	$css_message = "Just use as a general color guide.";

	$project_statuses = explode(",", get_option('bugerator_statuses'));

	$my_source = "";
	
	$source = "list.";
	$results[0]->id = 1;
	$results[0]->name = "Sample Project 1";
	$results[0]->status = 1;
	$results[0]->thisversion = "0.0";
	$results[0]->next_version = "0.1";
	$results[0]->next_date = "8/15/2012";
	$results[1]->id = 2;
	$results[1]->name = "Sample Project 2";
	$results[1]->status = 1;
	$results[1]->thisversion = "0.0";
	$results[1]->next_version = "0.1";
	$results[1]->next_date = "8/15/2012";
	$results[2]->id = 3;
	$results[2]->name = "Sample Project 3";
	$results[2]->status = 1;
	$results[2]->thisversion = "0.0";
	$results[2]->next_version = "0.1";
	$results[2]->next_date = "8/15/2012";

	ob_start();
	include("$path/bugerator_choose_project_tpl.php");
	$page = ob_get_clean();

	ob_start();
	include("$path/bugerator_options_css_edit_tpl.php");
	$content = ob_get_clean();

	return $content;
    }

    /**
     * Just puts together the sample data to view options for css editing
     * 
     * @global type $path
     * @global type $post
     * @param type $css_array
     * @return type
     */
    function bugerator_option_css_issue_list($css_array) {
	global $path;
	global $post;

	// Get the project status options.  All are comma delimeted
	$statuses = explode(",", get_option('bugerator_statuses'));
	$status_colors = explode(",", get_option('bugerator_status_colors'));
	$status_text_colors = explode(",", get_option('bugerator_status_text_colors'));
	// since we left some extra future default expansion we need to know what's in use.
	$statuses_used = explode(",", get_option('bugerator_statuses_inuse'));

	$date = "8/15/2012";

	$nonce = wp_create_nonce('bugerator_options');
	$error = "Sample Error.";
	$css_message = "This is a sample of the list of issues.";
	$message = "Sample Message.";
	$is_admin = true;

	$output_array = array(
	    array(
		"link" => "<a href='?page=bugerator_menu&tab=change_css&subtab=issue_list_css' class='bugerator_issue_link' >",
		"id" => "1",
		"status" => $statuses[1],
		"title" => "Sample title.",
		"submitter" => "Sample name",
		"assigned" => "Sample name",
		"priority" => "3",
		"version" => "1.0",
		"date" => $date,
		"style" => "background: " . $status_colors[1] . "; color: " . $status_text_colors[1] . ";",
		"completed" => ""
	    ),
	    array(
		"link" => "<a href='?page=bugerator_menu&tab=change_css&subtab=issue_list_css' class='bugerator_issue_link' >",
		"id" => "2",
		"status" => $statuses[2],
		"title" => "Sample title.",
		"submitter" => "Sample name",
		"assigned" => "Sample name",
		"priority" => "3",
		"version" => "1.0",
		"date" => $date,
		"style" => "background: " . $status_colors[2] . "; color: " . $status_text_colors[2] . ";",
		"completed" => ""
	    ), array(
		"link" => "<a href='?page=bugerator_menu&tab=change_css&subtab=issue_list_css' class='bugerator_issue_link' >",
		"id" => "3",
		"status" => $statuses[3],
		"title" => "Sample title.",
		"submitter" => "Sample name",
		"assigned" => "Sample name",
		"priority" => "3",
		"version" => "1.0",
		"date" => $date,
		"style" => "background: " . $status_colors[3] . "; color: " . $status_text_colors[3] . ";",
		"completed" => ""
	    )
	);



	$version_list = array("0.9", "1.0");
	$big_user_list = array();
	$statuses = explode(",", get_option('bugerator_statuses'));
	$statuses_in_use = explode(",", get_option('bugerator_statuses_inuse'));


	// now parse the css for the form
	$this_css = BugeratorMenu::css_parse($css_array['bugerator_css_issue_list']);
	$general_css = BugeratorMenu::css_parse($css_array['bugerator_css_all']);
	$css_form = BugeratorMenu::get_css_change_form($this_css, "bugerator_css_issue_list");
	$global_css_form = BugeratorMenu::get_css_change_form($general_css, "bugerator_css_all");


	ob_start();
	include("$path/bugerator_issue_list_tpl.php");
	$page = ob_get_clean();


	ob_start();
	include("$path/bugerator_options_css_edit_tpl.php");
	$content = ob_get_clean();


	return $content;
    }

    /**
     * This puts together a sample issue detail page for css editing
     * 
     * @global type $path
     * @global type $post
     * @param type $css_array
     * @return type
     */
    function bugerator_option_css_issue_detail($css_array) {
	global $path;
	global $post;

	// Get the project status options.  All are comma delimeted
	$statuses = explode(",", get_option('bugerator_statuses'));
	$status_colors = explode(",", get_option('bugerator_status_colors'));
	$status_text_colors = explode(",", get_option('bugerator_status_text_colors'));
	// since we left some extra future default expansion we need to know what's in use.
	$statuses_used = explode(",", get_option('bugerator_statuses_inuse'));

	$date = "8/15/2012";

	$nonce = wp_create_nonce('bugerator_options');
	$error = "Sample Error.";
	$message = "Sample Message.";
	$css_message = "This is a sample of the issue detail.";
	$is_admin = true;
	$upload_files = true;

	ob_start();
	include("$path/bugerator_issue_detail_tpl.php");
	$detail_page = ob_get_clean();




	$search_array = array("_MESSAGE_", "_ERROR_", "_ID_", "_TITLE_", "_STATUS_",
	    "_VERSION_", "_PRIORITY_", "_ASSIGNED_USER_",
	    "_SUBMITTED_USER_", "_FILE_ATTACHED_", "_TYPE_", "_SUBMITTED_DATE_",
	    "_UPDATED_", "_DESCRIPTION_", "TITLE_STYLE", "_STYLE_",
	    "_FILE_TEXT_", "_JAVASCRIPT_");
	$replace_array = array($message, $error, "1", "Sample issue", $statuses[2],
	    "1.0", "3", "Sample User", "Sample User", "sample_file.txt", "Bug", $date,
	    $date, nl2br("This is a sample description.\r\nYou would see this"),
	    "background: " . $status_colors[2] . "; color: " . $status_text_colors[2] . ";",
	    $css_array['bugerator_css_issue_detail'], "", "");

	$page = str_replace($search_array, $replace_array, $detail_page);

	// now parse the css for the form
	$this_css = BugeratorMenu::css_parse($css_array['bugerator_css_issue_detail']);
	$general_css = BugeratorMenu::css_parse($css_array['bugerator_css_all']);
	$css_form = BugeratorMenu::get_css_change_form($this_css, "bugerator_css_issue_detail");
	$global_css_form = BugeratorMenu::get_css_change_form($general_css, "bugerator_css_all");



	ob_start();
	include("$path/bugerator_options_css_edit_tpl.php");
	$content = ob_get_clean();

	return $content;
    }

    /**
     * Edit the color of the statuses - new, approved, etc.
     * @global type $path
     * @global type $post
     * @return type
     */
    function bugerator_option_css_status_colors() {
	global $path;
	global $post;
	$nonce = wp_create_nonce('bugerator_options');

	// Get the project status options.  All are comma delimeted
	$statuses = explode(",", get_option('bugerator_statuses'));
	$status_colors = explode(",", get_option('bugerator_status_colors'));
	$status_text_colors = explode(",", get_option('bugerator_status_text_colors'));
	// since we left some extra future default expansion we need to know what's in use.
	$statuses_used = explode(",", get_option('bugerator_statuses_inuse'));

	ob_start();
	include("$path/bugerator_options_status_colors_tpl.php");
	$content = ob_get_clean();
	return $content;
    }

    /**
     * Css for the New Issue form
     * 
     * @global type $path
     * @global type $post
     * @param type $css_array
     * @return type
     */
    function bugerator_option_css_add_issue($css_array) {
	global $path;
	global $post;
	global $filesize;
	$nonce = wp_create_nonce('bugerator_options');

	$default_version = 0;
	$developers = BugeratorMain::get_developers(1);
	
	$statuses = explode(",",get_option('bugerator_statuses'));


	$general_css = BugeratorMenu::css_parse($css_array['bugerator_css_all']);
	$page_css = BugeratorMenu::css_parse($css_array['bugerator_css_add_issue']);

	$global_css_form = BugeratorMenu::get_css_change_form($general_css, "bugerator_css_all");
	$css_form = BugeratorMenu::get_css_change_form($page_css, "bugerator_css_add_issue");
	$css_message = "Just use as a general color guide.";

	// dummy info for the page
	$project_name = "Sample Project";
	$types = explode(",", get_option('bugerator_types'));
	$admin = true;
	$versions = array("0.0", "0.1");
	$upload_files = true;

	ob_start();
	include("$path/bugerator_add_issue_tpl.php");
	$page = ob_get_clean();

	ob_start();
	include("$path/bugerator_options_css_edit_tpl.php");
	$content = ob_get_clean();

	return $content;
    }

    /**
     * Css for the version map page
     *
     * @global type $path
     * @global type $post
     * @param type $css_array
     * @return type
     */
    function bugerator_option_css_version_map($css_array) {
	global $path;
	global $post;
	$nonce = wp_create_nonce('bugerator_options');
	if (!isset($post->guid)) {
	    $post = new stdClass();
	    $post->guid = "";
	}
	$general_css = BugeratorMenu::css_parse($css_array['bugerator_css_all']);
	$page_css = BugeratorMenu::css_parse($css_array['bugerator_css_version_map']);
	$global_css_form = BugeratorMenu::get_css_change_form($general_css, "bugerator_css_all");
	$css_form = BugeratorMenu::get_css_change_form($page_css, "bugerator_css_version_map");
	$css_message = "Just use as a general color guide.";
	$date = "8/15/2012";

	// Get the project status options.  All are comma delimeted
	$statuses = explode(",", get_option('bugerator_statuses'));
	$status_colors = explode(",", get_option('bugerator_status_colors'));
	$status_text_colors = explode(",", get_option('bugerator_status_text_colors'));
	// since we left some extra future default expansion we need to know what's in use.
	$statuses_used = explode(",", get_option('bugerator_statuses_inuse'));


	$project_info = new stdClass();
	$results[0] = new stdClass();
	$results[1] = new stdClass();
	$results[2] = new stdClass();
	// dummy info for the page
	$project_info->name = "Sample Project";
	$types = explode(",", get_option('bugerator_types'));
	$version_list = array("0.0", "0.1");
	$goal_list = array("12/1/2012", "12/2/2012");

	$status_backgrounds = explode(",", get_option('bugerator_status_colors'));
	$status_text = explode(",", get_option('bugerator_status_text_colors'));
	$status_text = explode(",", get_option('bugerator_status_text_colors'));
	for ($x = 0; $x < count($status_text); $x++) {
	    $style[$x] = "background: " . $status_backgrounds[$x] . "; color: " . $status_text[$x] . ";";
	}

	$project = "Sample Project";
	$results[0]->id = "1";
	$results[0]->status = "1";
	$results[0]->version = 1;
	$results[0]->type = 1;
	$results[0]->title = "Sample title.";
	$results[0]->priority = "3";
	$results[1]->id = "2";
	$results[1]->status = "2";
	$results[1]->version = 0;
	$results[1]->type = 1;
	$results[1]->title = "Sample title 2.";
	$results[1]->priority = "3";
	$results[2]->id = "3";
	$results[2]->status = 3;
	$results[2]->version = 1;
	$results[2]->type = 0;
	$results[2]->title = "Sample title 3.";
	$results[2]->priority = "3";


	ob_start();
	include("$path/bugerator_show_map_tpl.php");
	$page = ob_get_clean();

	ob_start();
	include("$path/bugerator_options_css_edit_tpl.php");
	$content = ob_get_clean();

	return $content;
    }

    /**
     * Returns a formatted page of editable css options for a page.
     * 
     * This just returns the form of css options based on our defaults.
     * $option is the get_option($option) value and can be found in the
     * bugerator.css file
     * @global type $post
     * @param type $option - array of css vaules
     * @param type $get_option - which page
     * @return string
     */
    function get_css_change_form($option, $get_option) {
	global $post;
	$common_css = array(
	    " ",
	    "background-color",
	    "border",
	    "color",
	    "font-size",
	    "font-weight",
	    "margin",
	    "padding",
	    "text-decoration"
	);

	$css_keys = array_keys($option);
	if ("bugerator_css_all" == $get_option)
	    $css_form = "<h2 style='text-align: center;'>Global CSS is below. This will affect all pages.</h2>\r\n";
	else
	    $css_form = "<h2 style='text-align: center;'>CSS Classes for this page are below.</h2>\r\n";

	$css_form .= "<h3 style='text-align: center;'>Type the property in the box or choose from the list.</h3>\r\n";
	// two columns
	$right = 1;
	// get the form we will be using
	// Go through and get one row for each css property
	$css_form .= "<table class='option_css_form'>\r\n<tr >\r\n";
	foreach ($css_keys as $key) {
	    if (0 == $right) {
		$css_form .= "<tr>\r\n<td>\r\n";
	    } else {
		$css_form .= "<td>\t\n";
	    }
	    $css_form .= "<h3>$key</h3>\r\n";
	    $css_form .= "<table class='option_css_form' >\r\n";
	    $css_form .= "<tr class='option_css_form'><th>CSS Property</th>\r\n<th>Value</th></tr>\r\n";

	    foreach ($option[$key] as $property => $value) {

		$css_form .= "<tr class='option_css_form'><td>$property</td>\r\n<td>$value</td><td>\r\n" .
			"<a onClick='delete_css_row(\"$key\",\"$property\",\"$get_option\")'>
                        Delete</a></td>\r\n</tr>\r\n";
	    }
	    // add a row with a javascript call
	    $css_form .= "<tr class='option_css_form' style='vertical-align: text-top;'>\r\n<td>\r\n" .
		    "<input name='property_$key' id='input_property_$key' >\r\n" .
		    "<br/><select id=\"css_add_$key\" onChange=(easy_pick_css('$key')) >\r\n";
	    foreach ($common_css as $css_property)
		$css_form .= "<option value=\"$css_property\">$css_property";
	    $css_form .= "</select>\r\n</td>\r\n<td><input name='value_$key' id='value_$key' >\r\n<br/>" .
		    "<span id='span_$key' ></span>\r\n</td>\r\n<td>
                    <input type=button name=submit class='button-primary' value='Add' " .
		    "onClick='add_css_row(\"$key\",\"$get_option\")'>\r\n</td></tr>\r\n";
	    $css_form .= "</table>\r\n";
	    if (2 == $right or isset($post->guid)) {
		$right = 0;
		$css_form .= "</td></tr>\r\n";
	    } else {
		$css_form .= "</td>";
	    }
	    $right++;
	}
	$css_form .= "</table>";
	return $css_form;
    }

    /**
     * Pares css to make an array
     * 
     * This takes in a filename or just a string of CSS and returns something
     * td.key { font-weight: bold; }
     * becomes
     * ['td.key']['font-weight']=>'bold'
     * @param type $file - filename or string
     * @return type
     */
    function css_parse($file) {
	if (is_file($file))
	    $css = file_get_contents($file);
	else
	    $css = $file;
	preg_match_all('/(?ims)([a-z0-9\s\.\:#_\-@,]+)\{([^\}]*)\}/', $css, $arr);
	$result = array();
	foreach ($arr[0] as $i => $x) {
	    $selector = trim($arr[1][$i]);
	    $rules = explode(';', trim($arr[2][$i]));
	    $rules_arr = array();
	    foreach ($rules as $strRule) {
		if (!empty($strRule)) {
		    $rule = explode(":", $strRule);
		    $rules_arr[trim($rule[0])] = trim($rule[1]);
		}
	    }

	    $selectors = explode(',', trim($selector));
	    foreach ($selectors as $strSel) {
		$result[$strSel] = $rules_arr;
	    }
	}
	return $result;
    }

    /**
     * Takes an array and creates css
     * 
     * Takes in an array of css like this:
     * ['td.bugerator']['font-weight']=>'bold'
     * and turns it in to this
     * td.bugerator { font-weight: bold; }
     * 
     * @param type $css_array
     * @return string
     */
    function css_unparse($css_array) {
	$output = "";
	foreach ($css_array as $key => $properties) {
	    $output .= "$key {\r\n";
	    foreach ($properties as $property => $value) {
		$output .= "\t$property: $value;\r\n";
	    }
	    $output .= "}\r\n";
	}
	return $output;
    }

}

/* * *******************************
 * Install Class
 * constains the install, update, deactivate, uninstall functions
 */

class BugeratorInstall {

    /**
     * Installs the database and the options
     */
    function install() {
	// instructions here http://codex.wordpress.org/Creating_Tables_with_Plugins
	global $wpdb;
	global $bugerator_version;
	global $upload_dir;
	$user = wp_get_current_user();
	$id = $user->ID;

	// create table. See function below for definition
	$sql = self::get_tables_sql();

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	foreach ($sql as $sql) {
	    dbDelta($sql);
	}

	if (!is_dir($upload_dir)) {
	    mkdir($upload_dir);
	    $fp = fopen("$upload_dir/WARNING_do_not_save_files_here.txt", "w");
	    fwrite($fp, "EVERYTHING in this directory will be deleted if you uninstall " .
		    "bugerator. This contains the uploads related to the posts. Do not save " .
		    "anything else!");
	    fclose($fp);
	    $fp = fopen("$upload_dir/.htaccess", "w");
	    fwrite($fp, "\tOrder Deny,Allow\r\n\tDeny from All\r\n<Files \"export.zip\">\r\n\tOrder Deny,Allow" .
		    "\r\n\tAllow from All\r\n</Files>");
	    fclose($fp);
	}


	// Add version info to options
	add_option("bugerator_version", $bugerator_version, '', 'no');

	// Comma dilimeted status messages. Limited to 2^32 bytes (a.k.a. 4.2 million)
	// so we should be ok length wise
	// Status messages
	// Since this is used addable I'm adding some future statuses so I can add to the default later
	add_option("bugerator_statuses", "New,Open,Assigned,In Progress,Testing,Duplicate,Need Info,Resolved,Closed," .
		"Abandoned,Completed,future1,future2,future3,future4,future5,future6,future7,future8", '', 'no');
	// sort numbers for the statuses
	add_option("bugerator_status_sort", "0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18", '', 'no');
	// So we know if we should display the "future1" options. These are index numbers
	add_option("bugerator_statuses_inuse", "0,1,2,3,4,5,6,7,8,9,10", '', 'no');

	// Status colors. This will be user editable
	$default_colors = self::get_default_colors();
	add_option("bugerator_status_colors", $default_colors[0], '', 'no');
	add_option("bugerator_status_text_colors", $default_colors[1], '', 'no');


	// Bugerator admins aren't necessarily admins of the blog although the admin level of the blog
	// will also be an admin here.
	add_option("bugerator_admins", "$id", '', 'no');
	add_option("bugerator_developers", "", '', 'no');
	// status listing for the project:
	$update_project_statuses = "Pre Alpha, Alpha, Beta Active Development, Beta, Release Candidate,
                                Release, Release Active Development, Release Mature, future1, furure2, future3, future4, future5,
                                future6, future7";
	add_option('bugerator_project_statuses', $update_project_statuses, '', 'no');
	add_option('bugerator_project_statuses_inuse', "0,1,2,3,4,5,6,7", '', 'no');
	/* options is a csv
	 * [0] = anonymous posting allowed
	 * [1] = upload files allowed
	 * [2] = date format according to PHP date function
	 * [3] = date long format
	 * [4] = default margin (to break out of narrow divs)
	 */
	add_option('bugerator_options', 'anonymous_post|true,upload_files|true,date_format|m/d/Y,' .
		'long_date_format|m/d/Y H:i:s T,margin|0,filesize|1048576', '', 'no');
	add_option('bugerator_types', 'Bug,Feature Request,Idea', '', 'no');

	// list of users who want to get an email about every single update they've subscribed to
	add_option('bugerator_subscribers_all_email', '', '', 'no');
    }

    /**
     * Checks the version in our file and runs db/option updates if necessary
     */
    function bugerator_update_check() {
	global $bugerator_version;
	global $upload_dir;
	if (get_option('bugerator_version') != $bugerator_version) {
	    // dbDelta will change / upgrade the table as necessary.
	    $sql = self::get_tables_sql();
	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    foreach ($sql as $sql) {
		dbDelta($sql);
	    }
	    update_option('bugerator_version', $bugerator_version);
	    $options = BugeratorMain::get_options();
	    $option_string = "anonymous_post|" . $options['anonymous_post'] . ",upload_files|" . $options['upload_files'] .
		    ",date_format|" . $options['date_format'] . ",long_date_format|" . $options['long_date_format'] .
		    ",margin|" . $options['margin'] . ",filesize|1048576";
	    update_option('bugerator_options', $option_string);
	    add_option('bugerator_subscribers_all_email', '', '', 'no');
	}
    }

    /**
     * Deactivates which in our case does nothing.  If testing mode is on this kills the install
     * 
     * @global boolean $testing_mode
     * @return boolean
     */
    function deactivate() {
	// honestly I can't think of anything to do here
	// It would be bad to uninstall anything and I don't really
	// want to kill the preferences so we'll just go with:
	// testing mode check:
	global $testing_mode;
	if (true == $testing_mode)
	    self::uninstall();
	return true;
    }

    /**
     * Uninstalls everything
     * 
     * This kills all options set by the file, kills the database tables, deletes all attached 
     * files, and deletes the file attachment directory.  No turning back here.
     * 
     * @global type $wpdb
     * @global string $bugerator_issue_table
     * @global string $bugerator_project_table
     * @global string $bugerator_notes_table
     * @global string $upload_dir
     */
    static function uninstall() {
	// delete everything and disappear
	global $wpdb;
	global $bugerator_issue_table;
	global $bugerator_project_table;
	global $bugerator_notes_table;

	// goodbye sweet database tables
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$sql = "DROP TABLE " . $bugerator_issue_table . ";";
	$wpdb->query($sql);

	$sql = "DROP TABLE " . $bugerator_project_table . ";";
	$wpdb->query($sql);

	$sql = "DROP TABLE " . $bugerator_notes_table . ";";
	$wpdb->query($sql);

	// delete upload directory and contents
	global $upload_dir;
	foreach (glob($upload_dir . "/*.*") as $file) {
	    unlink($file);
	}
	rmdir($upload_dir);

	// Goodbye options, I barely knew thee
	delete_option("bugerator_version");
	delete_option("bugerator_statuses");
	delete_option("bugerator_status_colors");
	delete_option("bugerator_status_text_colors");
	delete_option("bugerator_statuses_inuse");
	delete_option("bugerator_admins");
	delete_option("bugerator_developers");
	delete_option('bugerator_project_statuses');
	delete_option('bugerator_project_statuses_inuse');
	delete_option('bugerator_options');
	delete_option('bugerator_types');
	foreach ($css as $option => $value)
	    delete_option($option);
    }

    /**
     * Returns an array of the table schema.  Is accessed by the update and the install.
     * 
     * @global string $bugerator_issue_table
     * @global string $bugerator_project_table
     * @global string $bugerator_notes_table
     * @return type
     */
    static function get_tables_sql() {
	global $bugerator_issue_table;
	global $bugerator_project_table;
	global $bugerator_notes_table;
	global $bugerator_subscriptions;

	// issue table
	// many issues per project.  Title, description, uploaded file, status (based on bugerator_statuses option),
	// Priority, version (so we can make a roadmap), date submitted & updated, who assigned to, if deleted,
	// list of users who will receive email when it changes
	$sql[0] = "CREATE TABLE $bugerator_issue_table (
                            id INT NOT NULL AUTO_INCREMENT,
                            project_id INT,
                            title VARCHAR(100),
                            type INT,
                            description TEXT,
                            filename VARCHAR(100),
                            status INT,
                            priority INT,
                            version INT,
                            submitted DATETIME,
                            updated DATETIME,
                            submitter BIGINT(20),
                            assigned BIGINT(20),
                            hidden TINYINT,
                            UNIQUE KEY id (id)
                        );
                        ";
	// notes table
	// There is a one to many relation between an issue and the related notes
	// so it needs its own table
	$sql[1] = "CREATE TABLE $bugerator_notes_table (
                            id INT NOT NULL AUTO_INCREMENT,
                            issue_id INT,
                            notes TEXT,
                            filename VARCHAR(100),
                            user BIGINT(20),
                            time DATETIME,
                            hidden TINYINT,
                            UNIQUE KEY id (id)
                        );
                        ;";

	// project table
	// Project name, owner id, current version, status (alpha, beta, free form text),
	// admins (as comma delimited text), developers
	// comma delimited list of available versions, dates versions created, goal dates, ids of subscribers
	$sql[2] = "CREATE TABLE $bugerator_project_table (
                            id INT NOT NULL AUTO_INCREMENT,
                            name VARCHAR(100),
                            owner BIGINT(20),
                            current_version INT,
                            version_date DATETIME,
                            status INT,
                            admins TEXT,
                            developers TEXT,
                            version_list TEXT,
                            version_created_list TEXT,
                            version_goal_list TEXT,
                            hidden TINYINT,
			    options TEXT,
                            UNIQUE KEY id (id)
                            );
                        ";
	/*
	 * Subscribers table
	 * type = "issue" or "project"
	 * user is the user id
	 * foreign_id = the id of the issue or project
	 */
	$sql[3] = "CREATE TABLE $bugerator_subscriptions (
			    id INT NOT NULL AUTO_INCREMENT,
			    user BIGINT(20),
			    type VARCHAR(20),
			    foreign_id INT,
			    visited TINYINT,
			    UNIQUE KEY id (id)
		);
		";
	return $sql;
    }

    /**
     * Default colors for the statuses.  IE new=red, assigned = green, etc.
     * 
     * @return string
     */
    static function get_default_colors() {
	// background colors first - These are status type colors for the header
	$default_colors[0] = "#FF6666,#FF6666,#66FF66,#AAFF66,#C299C2,#CCCCCC,#CCCCCC,#CCCCCC," .
		"#CCCCCC,#CCCCCC,#CCCCCC,#FFFFFF,#FFFFFF,#FFFFFF,#FFFFFF,#FFFFFF,#FFFFFF,#FFFFFF,#FFFFFF";
	// text colors
	$default_colors[1] = "#000000,#000000,#000000,#000000,#000000,#000000,#000000,#000000," .
		"#000000,#000000,#000000,#000000,#000000,#000000,#000000,#000000,#000000,#000000,#000000";
	return $default_colors;
    }

}

?>