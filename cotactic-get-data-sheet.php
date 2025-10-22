<?php
/**
 * Plugin Name: Cotactic Google Sheets Data (AJAX Edition)
 * Description: Fetch Google Sheet via admin, cache it, and display via AJAX front-end with Tailwind grid.
 * Version: 2.0
 * Author: Cotactic
 */

if (!defined('ABSPATH')) exit;

/*--------------------------------------------------------------
# 1. Admin Page (Fetch & Clear Cache)
--------------------------------------------------------------*/
add_action('admin_menu', function () {
    add_menu_page('CGSD Fetch', 'CGSD Fetch', 'manage_options', 'cgsd-fetch', 'cgsd_admin_page', '', 20);
});

function cgsd_admin_page() {
    $nonce = wp_create_nonce('cgsd_admin_nonce');
    ?>
<div class="wrap">
  <h1>CGSD Fetch Data</h1>

  <form method="post" action="options.php">
    <?php
          settings_fields('cgsd_settings');
          do_settings_sections('cgsd-fetch');
          submit_button('Save Settings');
        ?>
  </form>

  <hr>

  <p>หลังจากบันทึกค่าแล้ว กดปุ่มเพื่อดึงข้อมูลจาก Google Sheets และแคชไว้</p>
  <p>
    <button id="cgsd-fetch-btn" class="button button-primary">Fetch Data</button>
    <button id="cgsd-clear-cache" class="button">Clear Cache</button>
  </p>
  <p id="cgsd-fetch-msg"></p>
</div>

<script>
  jQuery(function ($) {
    $('#cgsd-fetch-btn').on('click', function () {
      $('#cgsd-fetch-msg').text('Fetching...');
      $.post(ajaxurl, {
        action: 'cgsd_fetch_sheet',
        _ajax_nonce: '<?php echo esc_js($nonce); ?>'
      }, function (res) {
        $('#cgsd-fetch-msg').text(res.success ? res.data : ('Error: ' + res.data));
      });
    });
    $('#cgsd-clear-cache').on('click', function () {
      $('#cgsd-fetch-msg').text('Clearing cache...');
      $.post(ajaxurl, {
        action: 'cgsd_clear_cache',
        _ajax_nonce: '<?php echo esc_js($nonce); ?>'
      }, function (res) {
        $('#cgsd-fetch-msg').text(res.success ? res.data : ('Error: ' + res.data));
      });
    });
  });
</script>
<?php
}

/*--------------------------------------------------------------
# 2. Admin AJAX for Fetch & Clear
--------------------------------------------------------------*/
add_action('wp_ajax_cgsd_clear_cache', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied.');
    check_ajax_referer('cgsd_admin_nonce');
    delete_transient('cgsd_sheet_data');
    wp_send_json_success('Cache cleared.');
});

add_action('wp_ajax_cgsd_fetch_sheet', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied.');
    check_ajax_referer('cgsd_admin_nonce');

    $sheet_id = get_option('cgsd_last_sheet_id');
    $range    = get_option('cgsd_last_range');
    $api_key  = get_option('cgsd_last_api_key');

    if (!$sheet_id || !$api_key) wp_send_json_error('Missing Sheet ID or API Key.');

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?key={$api_key}";
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'sslverify' => false,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Fetch error: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);

    // ✅ ลบ BOM และ whitespace ก่อน decode
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
    $body = trim($body);

    $data = json_decode($body, true);

    // ✅ debug ถ้าต้องการเช็กว่ามี keys อะไรบ้าง
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('JSON decode error: ' . json_last_error_msg() . ' | Raw: ' . substr($body, 0, 200));
    }

    if (empty($data) || empty($data['values'])) {
        // ✅ ถ้า Google ส่ง array ตรงๆ โดยไม่มี key “values”
        if (isset($data[0]) && is_array($data[0])) {
            $values = $data;
        } else {
            wp_send_json_error("No data found or invalid response structure. URL used: {$url}");
        }
    } else {
        $values = $data['values'];
    }

    // ✅ เก็บ cache
    set_transient('cgsd_sheet_data', $values, YEAR_IN_SECONDS);

    wp_send_json_success('Data fetched and cached successfully!');
});


/*--------------------------------------------------------------
# 3. Admin Settings
--------------------------------------------------------------*/
add_action('admin_init', function () {
    register_setting('cgsd_settings', 'cgsd_last_sheet_id');
    register_setting('cgsd_settings', 'cgsd_last_range');
    register_setting('cgsd_settings', 'cgsd_last_api_key');

    add_settings_section('cgsd_main', 'Google Sheets Settings', function () {
        echo '<p>กรอกข้อมูล Google Sheets แล้วกด Save</p>';
    }, 'cgsd-fetch');

    add_settings_field('sheet_id', 'Sheet ID', function () {
        printf('<input type="text" name="cgsd_last_sheet_id" class="regular-text" value="%s">', esc_attr(get_option('cgsd_last_sheet_id')));
    }, 'cgsd-fetch', 'cgsd_main');

    add_settings_field('range', 'Range', function () {
        printf('<input type="text" name="cgsd_last_range" class="regular-text" value="%s">', esc_attr(get_option('cgsd_last_range', 'Sheet1!A:H')));
    }, 'cgsd-fetch', 'cgsd_main');

    add_settings_field('api_key', 'API Key', function () {
        printf('<input type="password" name="cgsd_last_api_key" class="regular-text" value="%s">', esc_attr(get_option('cgsd_last_api_key')));
    }, 'cgsd-fetch', 'cgsd_main');
});

/*--------------------------------------------------------------
# 4. AJAX Endpoint for Frontend (GET Cached Data)
--------------------------------------------------------------*/
add_action('wp_ajax_nopriv_cgsd_get_data', 'cgsd_get_data_ajax');
add_action('wp_ajax_cgsd_get_data', 'cgsd_get_data_ajax');
function cgsd_get_data_ajax() {
    $data = get_transient('cgsd_sheet_data');
    if (empty($data) || count($data) < 2) {
        wp_send_json_error('No cached data found.');
    }
    wp_send_json_success($data);
}

/*--------------------------------------------------------------
# 5. Shortcode & Frontend Loader
--------------------------------------------------------------*/
function cgsd_ajax_shortcode() {
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'cgsd-fetch-js',
        plugin_dir_url(__FILE__) . 'dist/js/cgsd.js', // ✅ อ้างอิงจากปลั๊กอินที่รันจริง
        ['jquery'],
        '2.0',
        true
    );
    wp_enqueue_style(
        'cgsd-fa',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
        [],
        '6.5.0'
    );
    wp_enqueue_style('cgsd', plugin_dir_url(__FILE__) . 'dist/css/app.css', [], '2.0');

    wp_localize_script('cgsd-fetch-js', 'cgsd_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    return '<div id="cgsd-container" class="cgsd-tailwind text-center text-gray-500">Loading Google Sheet data...</div>';
}
add_shortcode('google_sheets_data', 'cgsd_ajax_shortcode');
