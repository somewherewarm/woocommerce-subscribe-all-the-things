<?php
/**
 * Templating and styling functions.
 *
 * @class 	WCCSubs_Display
 * @version 1.0.0
 */

class WCCSubs_Display {

	public static function init() {

		add_action( 'wp_enqueue_scripts', __CLASS__ . '::frontend_scripts' );
	}

	public static function frontend_scripts() {

		wp_register_style( 'wccsubs-css', WCCSubs()->plugin_url() . '/assets/css/wccsubs-frontend.css', false, WCCSubs::VERSION, 'all' );
		wp_enqueue_style( 'wccsubs-css' );
	}

}

WCCSubs_Display::init();
