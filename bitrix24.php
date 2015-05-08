<?php
/*
Plugin Name: Gravity Forms Bitrix24 Add-On
Plugin URI: http://www.gravityforms.com
Description: Integrates Gravity Forms with Bitrix24 allowing form submissions to be automatically sent to your Bitrix24 account
Version: 3.0
Author: rocketgenius
Author URI: http://www.rocketgenius.com
Text Domain: gravityformsbitrix24
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009 rocketgenius

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define ('GF_BITRIX24_VERSION', '3.1');

add_action( 'gform_loaded', array( 'GF_Bitrix24_Bootstrap', 'load' ), 5 );

add_action('init', 'gf_vendor_autoload', 1);

function gf_vendor_autoload()
{
	require_once get_site_home_path().'/vendor/autoload.php';
}

class GF_Bitrix24_Bootstrap {

	public static function load(){

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-bitrix24.php' );

		GFAddOn::register( 'GFBitrix24' );
	}
}

function gf_bitrix24(){
	return GFBitrix24::get_instance();
}

function get_site_home_path() {
	$home    = set_url_scheme( get_option( 'home' ), 'http' );
	$siteurl = set_url_scheme( get_option( 'siteurl' ), 'http' );
	if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
		$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
		$pos = strripos( str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ), trailingslashit( $wp_path_rel_to_home ) );
		$home_path = substr( $_SERVER['SCRIPT_FILENAME'], 0, $pos );
		$home_path = trailingslashit( $home_path );
	} else {
		$home_path = ABSPATH;
	}

	return str_replace( '\\', '/', $home_path );
}