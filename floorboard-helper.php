<?php
/**
 * Plugin Name:       Floorboard Helper
 * Plugin URI:        https://floorboardai.com/
 * Description:       A collection of helpers to run FloorboardAI.com
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Keanan Koppenhaver
 * Author URI:        https://keanankoppenhaver.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       floorboard-helper
 * Domain Path:       /languages
 */

add_action( 'init', 'register_floorboard_acf_blocks' );
function register_floorboard_acf_blocks() {
    register_block_type( __DIR__ . '/blocks/drafts' );
}
