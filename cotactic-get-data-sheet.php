<?php
/**
 * Plugin Name: Cotactic Google Sheets JS
 * Description: Fetch Google Sheet and render as Tailwind grid via JS
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

// Enqueue Tailwind + custom JS
function cgsd_enqueue_scripts() {
    // Tailwind CSS CDN
    wp_enqueue_script('cgsd-tailwind', 'https://cdn.tailwindcss.com', [], null, true);

    // Custom JS
    wp_enqueue_script(
        'cgsd-js',
        plugin_dir_url(__FILE__) . 'assets/js/cgsd.js',
        ['jquery'],
        '1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'cgsd_enqueue_scripts');

// Shortcode container
function cgsd_google_sheet_container($atts) {
    $atts = shortcode_atts([
        'sheet_id' => '1XRp8JMgl-B0gB8kTgfdtNFATmgGIJWRMMKgcOjVsclQ',  // ใส่ Google Sheet ID
        'range'    => 'Raw!A1:H',
        'api_key'  => 'AIzaSyBqUmwAM8Ubuf7pnpBipJgsSvG9IjxbDlc',  // ใส่ API Key ของคุณ
    ], $atts, 'google_sheets_data');

    // ส่ง attributes ให้ JS
    $atts_json = wp_json_encode($atts);

    return '<div id="cgsd-sheet-container" data-attrs=\'' . $atts_json . '\'></div>';
}
add_shortcode('google_sheets_data', 'cgsd_google_sheet_container');
