<?php
/**
 * Plugin Name: Cotactic Google Sheets Data Admin Fetch
 * Description: Fetch Google Sheet via admin button, render Tailwind grid, persistent cache until next fetch.
 * Version: 1.1
 */

if (!defined('ABSPATH')) exit;

// ---------- Enqueue Tailwind + JS ----------
function cgsd_enqueue_scripts() {
    wp_enqueue_script('cgsd-tailwind', 'https://cdn.tailwindcss.com', [], null, true);
    wp_enqueue_script('cgsd-js', plugin_dir_url(__FILE__) . 'assets/js/cgsd.js', ['jquery'], '1.1', true);

    // AJAX URL
    wp_localize_script('cgsd-js', 'cgsd_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'cgsd_enqueue_scripts');

// ---------- Admin Menu ----------
add_action('admin_menu', function() {
    add_menu_page('CGSD Fetch', 'CGSD Fetch', 'manage_options', 'cgsd-fetch', 'cgsd_admin_page', '', 20);
});

function cgsd_admin_page() {
    ?>
<div class="wrap">
  <h1>CGSD Fetch Data</h1>
  <p>Click the button to fetch and cache data from Google Sheet.</p>
  <button id="cgsd-fetch-btn" class="button button-primary">Fetch Data</button>
  <p id="cgsd-fetch-msg"></p>
</div>
<script>
  jQuery(document).ready(function ($) {
    $('#cgsd-fetch-btn').click(function () {
      $('#cgsd-fetch-msg').text('Fetching...');
      $.post(ajaxurl, {
        action: 'cgsd_fetch_sheet'
      }, function (response) {
        if (response.success) {
          $('#cgsd-fetch-msg').text(response.data);
        } else {
          $('#cgsd-fetch-msg').text('Error: ' + response.data);
        }
      });
    });
  });
</script>
<?php
}

// ---------- AJAX Handler ----------
add_action('wp_ajax_cgsd_fetch_sheet', function() {
    // รับค่า Sheet ID / Range / API Key จาก transient หรือจาก admin JS
    // ในเวอร์ชันนี้ ใช้ค่าที่ shortcode กำหนดหรือค่าตั้งต้น
    $sheet_id = isset($_POST['sheet_id']) ? sanitize_text_field($_POST['sheet_id']) : get_option('cgsd_last_sheet_id', '');
    $range    = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : get_option('cgsd_last_range', 'Raw!A:H');
    $api_key  = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : get_option('cgsd_last_api_key', '');

    if (!$sheet_id || !$api_key) {
        wp_send_json_error('Missing Sheet ID or API Key.');
    }

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?key={$api_key}";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error('Error fetching sheet: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['values'])) {
        wp_send_json_error('No data found in sheet.');
    }

    // Save transient แบบยาว จนกว่าจะ fetch ใหม่ (1 ปี)
    set_transient('cgsd_sheet_data', $data['values'], YEAR_IN_SECONDS);

    // บันทึกค่าที่ใช้ล่าสุด (สำหรับ default)
    update_option('cgsd_last_sheet_id', $sheet_id);
    update_option('cgsd_last_range', $range);
    update_option('cgsd_last_api_key', $api_key);

    wp_send_json_success('Data fetched and cached successfully!');
});

// ---------- Shortcode ----------
function cgsd_sheet_shortcode($atts) {
    $atts = shortcode_atts([
        'sheet_id' => get_option('cgsd_last_sheet_id', ''),
        'api_key' => get_option('cgsd_last_api_key', ''),
        'range' => get_option('cgsd_last_range', 'Raw!A:H'),
    ], $atts, 'google_sheets_data');

    $values = get_transient('cgsd_sheet_data');
    if (empty($values) || count($values) < 2) {
        return '<p class="text-yellow-700">No data available. Click fetch in admin.</p>';
    }

    $headers = $values[0];
    $rows = array_slice($values, 1);

    $url_pattern = '/^(https?:\/\/)?([\w.-]+)\.([a-zA-Z]{2,})([\w\/-]*)?$/';

    $html = '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-6">';

    foreach ($rows as $r) {
        $obj = [];
        foreach ($headers as $i => $h) $obj[$h] = isset($r[$i]) ? $r[$i] : '';

        $website  = $obj['Website'] ?? '';
        $facebook = $obj['Facebook Page'] ?? '';

        $html .= '
        <article class="group relative rounded-2xl ring-1 ring-gray-200 bg-white hover:shadow-xl transition-shadow">
            <div class="font-bold text-lg text-center bg-[#0B284D] text-[#FED312] py-1 rounded-t-xl">' . esc_html($obj['Agency Name'] ?? '—') . '</div>
            <div class="p-4">
              <div class="mt-2 text-sm text-gray-700"><strong>Website:</strong> ' . esc_html($website) . '</div>
              <div class="text-sm text-gray-700"><strong>Facebook:</strong> ' . esc_html($facebook) . '</div>
              <div class="text-sm text-gray-700"><strong>Phone:</strong> ' . esc_html($obj['Phone Number'] ?? '') . '</div>
            </div>
            <div class="flex items-center justify-center pb-2 gap-3">';

        // Website button
        if (!empty($website) && preg_match($url_pattern, $website)) {
            $html .= '<a href="' . esc_url($website) . '" target="_blank" rel="noopener" class="inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-[#0B284D]/90 hover:text-[#FED312] bg-[#0B284D] text-[#FED312]">View</a>';
        }

        // Facebook button
        if (!empty($facebook) && preg_match($url_pattern, $facebook)) {
            $html .= '<a href="' . esc_url($facebook) . '" target="_blank" rel="noopener" class="inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-gray-50">Facebook</a>';
        }

        $html .= '</div></article>';
    }

    $html .= '</div>';

    return $html;
}
add_shortcode('google_sheets_data', 'cgsd_sheet_shortcode');
