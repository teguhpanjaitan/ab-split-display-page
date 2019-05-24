<?php
/**
 * @package Split Page in One
 * @version 1.0.0
 */
/*
Plugin Name: Split Page in One
Plugin URI:
Description: This plugin will able to display different page in one url based on the rule which you can set on the backend
Author: Teguh putra utama
Version: 1.0.0
Author URI:
*/

add_shortcode( 'splitone', 'split_the_page' );

function split_the_page( $atts ){
	return "foo and bar";
}

// add_action( 'admin_menu', 'insta_rets_menu' );

// function insta_rets_menu() {
  // add_menu_page(
    // 'Insta RETS Setting',
    // 'Different Page',
    // 'manage_options',
    // 'different_page',
    // 'route_it',
    // 'dashicons-format-gallery'
  // );

// }

// function route_it(){
	// return "Hello Ehlo";
   // $loader = new Loader(plugin_dir_path(__DIR__));
   // $loader->Run();
// }