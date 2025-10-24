<?php
/**
 * Plugin Name: Cotactic Google Sheets → DB
 * Description: ดึง Google Sheets มาเก็บใน DB (get_data_sheets) แล้วค่อยดึงจาก DB มาแสดงผล + ปุ่ม Fetch/Clear ในแอดมิน
 * Version:     2.0.0
 * Author:      Cotactic
 */

if (!defined('ABSPATH')) exit;

define('CGSD_VER', '2.0.0');
define('CGSD_SLUG', 'cotactic-get-data-sheet');
define('CGSD_TABLE', $GLOBALS['wpdb']->prefix . 'get_data_sheets');

/** -----------------------------------------------------------
 * 1) สร้างตารางเมื่อเปิดใช้งานปลั๊กอิน
 * ----------------------------------------------------------- */
register_activation_hook(__FILE__, function () {
    error_log("✅ CGSD ACTIVATION HOOK RUNNING...");
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table   = $wpdb->prefix . 'get_data_sheets';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        agency_name VARCHAR(255) DEFAULT '' NOT NULL,
        website TEXT,
        facebook TEXT,
        phone VARCHAR(50),
        logo TEXT,
        meta_desc TEXT,
        first_letter VARCHAR(8) DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    error_log("✅ CGSD TABLE CREATION DONE for {$table}");
});

/** -----------------------------------------------------------
 * 2) เมนูแอดมิน + หน้า Settings
 * ----------------------------------------------------------- */
add_action('admin_menu', function () {
    add_menu_page(
        'CGSD: Sheets → DB',
        'CGSD Sheets → DB',
        'manage_options',
        'cgsd-db',
        'cgsd_admin_page',
        'dashicons-database-import',
        20
    );
});

function cgsd_admin_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    $nonce = wp_create_nonce('cgsd_admin');
    $sheet_id = esc_attr(get_option('cgsd_sheet_id', ''));
    $range    = esc_attr(get_option('cgsd_range', 'Sheet1!A:H'));
    $api_key  = esc_attr(get_option('cgsd_api_key', ''));

    ?>
<div class="wrap">
  <h1>CGSD: Google Sheets → Database</h1>
  <p>ปลั๊กอินนี้จะดึงข้อมูลจาก Google Sheets มาเก็บในตาราง <code><?php echo CGSD_TABLE; ?></code>
    จากนั้นหน้าเว็บจะอ่านจาก DB เท่านั้น</p>

  <h2 class="title">Google Sheets Settings</h2>
  <table class="form-table">
    <tr>
      <th scope="row">Sheet ID</th>
      <td><input type="text" id="cgsd_sheet_id" class="regular-text" value="<?php echo $sheet_id; ?>"></td>
    </tr>
    <tr>
      <th scope="row">Range</th>
      <td><input type="text" id="cgsd_range" class="regular-text" value="<?php echo $range; ?>"
          placeholder="เช่น 200Digital!A:H"></td>
    </tr>
    <tr>
      <th scope="row">API Key</th>
      <td>
        <input type="password" id="cgsd_api_key" class="regular-text" value="<?php echo $api_key; ?>">
        <label><input type="checkbox" id="cgsd_toggle_api"> Show</label>
      </td>
    </tr>
  </table>

  <p>
    <button id="cgsd_save_settings" class="button">Save Settings</button>
    <button id="cgsd_fetch" class="button button-primary">Fetch Data → DB</button>
    <button id="cgsd_clear" class="button">Clear Database</button>
  </p>

  <p id="cgsd_msg"></p>
</div>

<script>
  window.CGSD_ADMIN = {
    nonce: "<?php echo esc_js($nonce); ?>",
    ajax: "<?php echo admin_url('admin-ajax.php'); ?>",
    sheet_id: "<?php echo $sheet_id; ?>",
    range: "<?php echo $range; ?>",
    api_key: "<?php echo $api_key; ?>"
  };
</script>
<?php
}

/** -----------------------------------------------------------
 * 3) Enqueue JS (admin + frontend)
 * ----------------------------------------------------------- */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_cgsd-db') return;
    wp_enqueue_script('cgsd-admin', plugins_url('dist/js/cgsd.js', __FILE__), ['jquery'], CGSD_VER, true);
});

add_action('wp_enqueue_scripts', function () {
    // fontawesome (ทางเลือก)
    wp_enqueue_style('cgsd-fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css', [], '6.5.0');
    // css เสริม (วางไฟล์เองได้)
    wp_enqueue_style('cgsd-frontend', plugins_url('dist/css/app.css', __FILE__), [], CGSD_VER);
    wp_enqueue_script('cgsd-frontend', plugins_url('dist/js/frontend.js', __FILE__), [], CGSD_VER, true);
    wp_localize_script('cgsd-frontend', 'cgsd_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
});

/** -----------------------------------------------------------
 * 4) AJAX: บันทึกค่า settings (ในหน้าแอดมิน)
 * ----------------------------------------------------------- */
add_action('wp_ajax_cgsd_save_settings', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission');
    check_ajax_referer('cgsd_admin', 'nonce');

    update_option('cgsd_sheet_id', sanitize_text_field($_POST['sheet_id'] ?? ''));
    update_option('cgsd_range',    sanitize_text_field($_POST['range'] ?? ''));
    update_option('cgsd_api_key',  sanitize_text_field($_POST['api_key'] ?? ''));

    wp_send_json_success('Saved.');
});

/** -----------------------------------------------------------
 * 5) AJAX: Fetch Google Sheets → Save DB
 * ----------------------------------------------------------- */
add_action('wp_ajax_cgsd_fetch_to_db', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission');
    check_ajax_referer('cgsd_admin', 'nonce');

    global $wpdb;
    $table = CGSD_TABLE;

    $sheet_id = sanitize_text_field($_POST['sheet_id'] ?? get_option('cgsd_sheet_id', ''));
    $range    = sanitize_text_field($_POST['range']    ?? get_option('cgsd_range', 'Sheet1!A:H'));
    $api_key  = sanitize_text_field($_POST['api_key']  ?? get_option('cgsd_api_key', ''));

    if (!$sheet_id || !$api_key) wp_send_json_error('Missing Sheet ID or API Key');

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?key={$api_key}";
    $res = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($res)) wp_send_json_error('HTTP error: '.$res->get_error_message());

    $body = wp_remote_retrieve_body($res);
    $data = json_decode($body, true);
    if (empty($data['values']) || count($data['values']) < 2) {
        wp_send_json_error('No data values');
    }

    $headers = array_shift($data['values']); // แถวหัวตาราง
    // เคลียร์ตารางก่อน
    $wpdb->query("TRUNCATE TABLE $table");

    $count = 0;
    foreach ($data['values'] as $row) {
        $obj = [];
        foreach ($headers as $i => $h) {
            $obj[$h] = $row[$i] ?? '';
        }

        $agency   = trim($obj['Agency Name'] ?? '');
        if ($agency === '') continue;

        $website  = trim($obj['Website'] ?? '');
        $facebook = trim($obj['Facebook Page'] ?? '');
        $phone    = trim($obj['Phone Number'] ?? '');
        $logo     = trim(($obj['URL Logo'] ?? '') ?: ($obj['Logo URL'] ?? ''));
        $desc     = trim(($obj['Meta Description (EN)'] ?? '') ?: ($obj['Meta Description (TH)'] ?? ''));

        $first    = mb_strtoupper(mb_substr($agency, 0, 1, 'UTF-8'));
        if (!preg_match('/[A-Z]/u', $first)) $first = '0-9';

        $wpdb->insert($table, [
            'agency_name'  => $agency,
            'website'      => $website,
            'facebook'     => $facebook,
            'phone'        => $phone,
            'logo'         => $logo,
            'meta_desc'    => $desc,
            'first_letter' => $first,
            'updated_at'   => current_time('mysql'),
        ]);
        $count++;
    }

    wp_send_json_success("Imported {$count} rows to DB.");
});

/** -----------------------------------------------------------
 * 6) AJAX: ล้าง Database
 * ----------------------------------------------------------- */
add_action('wp_ajax_cgsd_clear_db', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission');
    check_ajax_referer('cgsd_admin', 'nonce');

    global $wpdb;
    $wpdb->query("TRUNCATE TABLE " . CGSD_TABLE);
    wp_send_json_success('Database cleared.');
});

/** -----------------------------------------------------------
 * 7) AJAX (public): ดึงข้อมูลจาก DB เพื่อแสดงหน้าเว็บ
 * ----------------------------------------------------------- */
add_action('wp_ajax_nopriv_cgsd_get_db_data', 'cgsd_get_db_data');
add_action('wp_ajax_cgsd_get_db_data',        'cgsd_get_db_data');

function cgsd_get_db_data() {
    global $wpdb;
    $rows = $wpdb->get_results("
        SELECT agency_name, website, facebook, phone, logo, meta_desc, first_letter
        FROM ".CGSD_TABLE."
        ORDER BY first_letter ASC, agency_name ASC
    ", ARRAY_A);

    wp_send_json_success($rows);
}

/** -----------------------------------------------------------
 * 8) Shortcode: [cgsd_sheet]
 *    แทรก container แล้วให้ JS ไปดึงจาก DB
 * ----------------------------------------------------------- */
add_shortcode('cgsd_sheet', function () {
    // ให้แน่ใจว่า frontend.js ถูกโหลด (หากยังไม่ถูก enqueue ในธีม)
    if (!wp_script_is('cgsd-frontend', 'enqueued')) {
        wp_enqueue_script('cgsd-frontend');
        wp_localize_script('cgsd-frontend', 'cgsd_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
    return '<div id="cgsd-container"></div>';
});

function cgsd_maybe_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'get_data_sheets';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) return;

    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        agency_name VARCHAR(255) DEFAULT '' NOT NULL,
        website TEXT, facebook TEXT, phone VARCHAR(50), logo TEXT,
        meta_desc TEXT, first_letter VARCHAR(8) DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'cgsd_maybe_create_table');
add_action('admin_init', 'cgsd_maybe_create_table');

add_shortcode('cgsd_sheet', function () {
    cgsd_maybe_create_table();
    return '<div id="cgsd-container"></div>';
});
