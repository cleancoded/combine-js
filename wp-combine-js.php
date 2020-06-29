<?php
/*
Plugin Name: Cleancoded Combine JS Plugin
Plugin URI: https://github.com/cleancoded/combine-js
Description: WordPress plugin that attempts to combine, minify, and compress JS.
Author: Cleancoded
Version: 1.0
Author URI: http://www.cleancoded.com
Requires at least: 3.0.0
Tested up to: 5.4
*/
?>
<?php

// don't allow direct access of this file

if ( preg_match( '#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'] ) ) die();

// require base objects and do instantiation

if ( !class_exists( 'CleancodedCombineJS' ) ) {
        require_once( dirname( __FILE__ ) . '/classes/combine-js.php' );
}
$cleancoded_combine_js = new CleancodedCombineJS();

// define plugin file path

$cleancoded_combine_js->set_plugin_file( __FILE__ );

// define directory name of plugin

$cleancoded_combine_js->set_plugin_dir( basename( dirname( __FILE__ ) ) );

// path to this plugin

$cleancoded_combine_js->set_plugin_path( dirname( __FILE__ ) );

// URL to plugin

$cleancoded_combine_js->set_plugin_url( plugin_dir_url(__FILE__) );

// call init

$cleancoded_combine_js->init();

?>
