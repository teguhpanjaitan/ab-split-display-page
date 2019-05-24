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

require_once("Loader.php");

add_shortcode( 'splitone', 'split_the_page' );

function split_the_page( $atts ){
	$loader = new Loader(plugin_dir_path(__DIR__));
     return $loader->executeShortcode($atts);
}