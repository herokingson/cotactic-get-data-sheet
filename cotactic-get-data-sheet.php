<?php
/**
 * Plugin Name: Cotactic Google Sheets Admin Fetch
 * Description: Fetch Google Sheet once via admin button, render Tailwind grid.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

// ---------- Enqueue Tailwind + JS ----------
function cgsd_enqueue_scripts() {
    wp_enqueue_script('cgsd-tailwind', 'https://cdn.tailwindcss.com', [], null, true);
    wp_enqueue_script('cgsd-js', plugin_dir_url(__FILE__) . 'assets/js/cgsd.js', ['jquery'], '1.0', true);
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
  <button id="cgsd-fetch-btn" class="button button-primary">Fetch Data from Google Sheet</button>
  <p id="cgsd-fetch-msg"></p>
</div>
<script>
  jQuery(document).ready(function ($) {
    $('#cgsd-fetch-btn').click(function () {
      $('#cgsd-fetch-msg').text('Fetching...');
      $.post(ajaxurl, {
        action: 'cgsd_fetch_sheet'
      }, function (response) {
        $('#cgsd-fetch-msg').text(response.data);
      });
    });
  });
</script>
<?php
}

// ---------- AJAX Handler ----------
add_action('wp_ajax_cgsd_fetch_sheet', function() {
    $sheet_id = '1XRp8JMgl-B0gB8kTgfdtNFATmgGIJWRMMKgcOjVsclQ';
    $range    = 'Raw!A:H';
    $api_key  = 'AIzaSyBqUmwAM8Ubuf7pnpBipJgsSvG9IjxbDlc'; // ใช้ API Key สำหรับ client-side หรือ Service Account สำหรับ server

    // Fetch Google Sheet JSON via API Key
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

    // Save to transient 1 day
    set_transient('cgsd_sheet_data', $data['values'], DAY_IN_SECONDS);

    wp_send_json_success('Data fetched and cached successfully!');
});

// ---------- Shortcode ----------
function cgsd_sheet_shortcode($atts) {
    $values = get_transient('cgsd_sheet_data');
    if (empty($values) || count($values) < 2) {
        return '<p class="text-yellow-700">No data available. Click fetch in admin.</p>';
    }

    $headers = $values[0];
    $rows = array_slice($values, 1);

    $html = '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-6">';

    foreach ($rows as $r) {
        $obj = [];
        foreach ($headers as $i => $h) $obj[$h] = isset($r[$i]) ? $r[$i] : '';

        $html .= '
        <article class="group relative rounded-2xl ring-1 ring-gray-200 bg-white hover:shadow-xl transition-shadow">
            <div class="font-bold text-lg text-center bg-[#0B284D] text-[#FED312] py-1 rounded-t-xl">' . esc_html($obj['Agency Name'] ?? '—') . '</div>
            <div class="p-4">
              <div class="mt-2 text-sm text-gray-700"><strong>Website:</strong> ' . esc_html($obj['Website'] ?? '') . '</div>
              <div class="text-sm text-gray-700"><strong>Facebook:</strong> ' . esc_html($obj['Facebook Page'] ?? '') . '</div>
              <div class="text-sm text-gray-700"><strong>Phone:</strong> ' . esc_html($obj['Phone Number'] ?? '') . '</div>
            </div>
         <div class="flex items-center justify-center pb-2 gap-3">';

        $url_pattern = '/^(https?:\/\/)?([\w.-]+)\.([a-zA-Z]{2,})([\w\/-]*)?$/';
        $website = $obj['Website'] ?? '';
        $facebook = $obj['Facebook Page'] ?? '';

        // ปุ่ม Website
        if (!empty($website) && preg_match($url_pattern, $website)) {
            $html .= '<a href="' . esc_url($website) . '" target="_blank" rel="noopener" class="inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-[#0B284D]/90 hover:text-[#FED312] bg-[#0B284D] text-[#FED312]">View</a>';
        }

        // ปุ่ม Facebook
        if (!empty($facebook) && preg_match($url_pattern, $facebook)) {
            $html .= '<a href="' . esc_url($facebook) . '" target="_blank" rel="noopener" class="inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-gray-50">Facebook</a>';
        }

        $html .= '</div></article>';
    }

    $html .= '</div>';

    return $html;
}
add_shortcode('google_sheets_data', 'cgsd_sheet_shortcode');
