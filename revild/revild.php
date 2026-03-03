<?php
/**
 * Plugin Name: ReviLD
 * Plugin URI:
 * Description: レビュー記事に Product + Review 構造化データ（JSON-LD）を出力するプラグイン
 * Version:     1.1.0
 * Author:      4536
 * License:     GPL-2.0-or-later
 * Text Domain: revild
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'REVILD_VERSION', '1.1.0' );
define( 'REVILD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REVILD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once REVILD_PLUGIN_DIR . 'includes/class-conflict-detector.php';
require_once REVILD_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once REVILD_PLUGIN_DIR . 'includes/class-schema-output.php';
require_once REVILD_PLUGIN_DIR . 'includes/class-review-box.php';

// --- Auto-update via GitHub Releases ---
require_once REVILD_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$revild_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/gadgetlogy2babi-web/revild',
    __FILE__,
    'revild'
);
$revild_update_checker->setBranch( 'main' );
$revild_update_checker->getVcsApi()->enableReleaseAssets();

add_action( 'init', function () {
    $conflict_detector = new Revild_Conflict_Detector();

    new Revild_Meta_Box();
    new Revild_Review_Box();
    new Revild_Schema_Output();

    $conflict_detector->register_admin_notice();
} );
