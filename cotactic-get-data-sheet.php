<?php
/**
 * Plugin Name: Cotactic Google Sheets → DB
 * Description: ดึง Google Sheets มาเก็บใน DB (get_data_sheets) แล้วค่อยดึงจาก DB มาแสดงผล + ปุ่ม Fetch/Clear ในแอดมิน
 * Version:     2.0.0
 * Author:      Cotactic
 */

if (!defined('ABSPATH'))
  exit;

define('CGSD_VER', '2.0.0');
define('CGSD_SLUG', 'cotactic-get-data-sheet');
define('CGSD_TABLE', $GLOBALS['wpdb']->prefix . 'get_data_sheets');

/** -----------------------------------------------------------
 * 1) สร้างตารางเมื่อเปิดใช้งานปลั๊กอิน
 * ----------------------------------------------------------- */
// register_activation_hook(__FILE__, function () {
//     error_log("✅ CGSD ACTIVATION HOOK RUNNING...");
//     global $wpdb;
//     $charset = $wpdb->get_charset_collate();
//     $table   = $wpdb->prefix . 'get_data_sheets';

//     $sql = "CREATE TABLE IF NOT EXISTS $table (
//         id INT UNSIGNED NOT NULL AUTO_INCREMENT,
//         agency_name VARCHAR(255) DEFAULT '' NOT NULL,
//         website TEXT,
//         facebook TEXT,
//         phone VARCHAR(50),
//         logo TEXT,
//         meta_desc TEXT,
//         first_letter VARCHAR(8) DEFAULT '',
//         updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//         PRIMARY KEY  (id)
//     ) $charset;";

//     require_once ABSPATH . 'wp-admin/includes/upgrade.php';
//     dbDelta($sql);

//     error_log("✅ CGSD TABLE CREATION DONE for {$table}");
// });

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
  $range = esc_attr(get_option('cgsd_range', 'Sheet1!A:H'));
  $api_key = esc_attr(get_option('cgsd_api_key', ''));

  ?>
  <div class="wrap">
    <h1>CGSD: Google Sheets → Database</h1>
    <p>ปลั๊กอินนี้จะดึงข้อมูลจาก Google Sheets มาเก็บในตาราง <code><?php echo CGSD_TABLE; ?></code>
      จากนั้นหน้าเว็บจะอ่านจาก DB เท่านั้น</p>

    <h2 class="title">Google Sheets Settings</h2>
    <table class="form-table">
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
      api_key: "<?php echo $api_key; ?>"
    };
  </script>
  <?php
}

/** -----------------------------------------------------------
 * 3) Enqueue JS (admin)
 * ----------------------------------------------------------- */
add_action('admin_enqueue_scripts', function ($hook) {
  if ($hook !== 'toplevel_page_cgsd-db')
    return;
  wp_enqueue_script('cgsd-admin', plugins_url('dist/js/cgsd.js', __FILE__), ['jquery'], CGSD_VER, true);
});

add_action('wp_enqueue_scripts', function () {
  // fontawesome (ทางเลือก)
  wp_enqueue_style('cgsd-fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css', [], '6.5.0');
  // css เสริม (วางไฟล์เองได้)
  wp_enqueue_style('cgsd', plugins_url('dist/css/app.css', __FILE__), [], CGSD_VER);
  wp_localize_script('cgsd-frontend', 'cgsd_vars', [
    'ajax_url' => admin_url('admin-ajax.php'),
  ]);
});


/** -----------------------------------------------------------
 * 4) AJAX: ล้าง Database
 * ----------------------------------------------------------- */
add_action('wp_ajax_cgsd_clear_db', function () {
  if (!current_user_can('manage_options'))
    wp_send_json_error('Permission');
  check_ajax_referer('cgsd_admin', 'nonce');

  global $wpdb;

  // หา tables ทั้งหมดที่เกี่ยวข้อง: wp_get_data_sheets และ wp_get_data_sheets_*
  $like = $wpdb->esc_like($wpdb->prefix . 'get_data_sheets') . '%';
  $tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $like));

  if (empty($tables))
    wp_send_json_success('No related tables found.');

  $cleared = 0;
  $fallback = 0;
  foreach ($tables as $t) {
    // พยายาม TRUNCATE ก่อน (เร็ว)
    $r = $wpdb->query("TRUNCATE TABLE `$t`");
    if ($r === false) {
      // โฮสต์บางเจ้าไม่อนุญาต → ใช้ DELETE + รีเซ็ต AUTO_INCREMENT แทน
      $wpdb->query("DELETE FROM `$t`");
      $wpdb->query("ALTER TABLE `$t` AUTO_INCREMENT = 1");
      $fallback++;
    }
    $cleared++;
  }

  wp_send_json_success("Cleared {$cleared} table(s)" . ($fallback ? " (fallback used on {$fallback})" : ""));
});


/** -----------------------------------------------------------
 * 5) AJAX (public): ดึงข้อมูลจาก DB เพื่อแสดงหน้าเว็บ
 * ----------------------------------------------------------- */
add_action('wp_ajax_nopriv_cgsd_get_db_data', 'cgsd_get_db_data');
add_action('wp_ajax_cgsd_get_db_data', 'cgsd_get_db_data');

function cgsd_get_db_data()
{
  global $wpdb;
  $rows = $wpdb->get_results("
        SELECT agency_name, website, facebook, phone, logo, meta_desc, first_letter
        FROM " . CGSD_TABLE . "
        ORDER BY first_letter ASC, agency_name ASC
    ", ARRAY_A);

  wp_send_json_success($rows);
}

/** -----------------------------------------------------------
 * 6) Shortcode: [cgsd_sheet]
 *    แทรก container แล้วให้ JS ไปดึงจาก DB
 * ----------------------------------------------------------- */
add_shortcode('cgsd_sheet', function ($atts) {
  global $wpdb;

  // 🧩 อ่าน attributes
  $atts = shortcode_atts([
    'sheet_id' => '',
    'range' => '',
    'api_key' => get_option('cgsd_api_key', ''),
    'force_refresh' => false,
    'cta_template_id' => '', // ID ของ Elementor template สำหรับ CTA
  ], $atts);

  $sheet_id = sanitize_text_field($atts['sheet_id']);
  $range = sanitize_text_field($atts['range']);
  $api_key = sanitize_text_field($atts['api_key']);
  $cta_template_id = sanitize_text_field($atts['cta_template_id']);

  if (!$sheet_id || !$range || !$api_key) {
    return '<p class="text-red-600">⚠️ Missing Sheet ID / Range / API Key</p>';
  }

  // 🧩 สร้าง table name เฉพาะสำหรับแต่ละ shortcode
  // ใช้ hash ป้องกันชื่อยาว / อักษรพิเศษ
  $hash = substr(md5($sheet_id . $range), 0, 8);
  $table = $wpdb->prefix . 'get_data_sheets_' . $hash;

  // ✅ ตรวจว่ามีตารางนี้หรือยัง ถ้ายัง → สร้างใหม่
  $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
  if ($exists !== $table) {
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            agency_name VARCHAR(255) DEFAULT '' NOT NULL,
            website TEXT,
            facebook TEXT,
            phone VARCHAR(50),
            logo TEXT,
            meta_desc TEXT,
            first_letter VARCHAR(8) DEFAULT '',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_letter (first_letter),
            KEY idx_agency (agency_name)
        ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }

  // 🔍 ตรวจว่ามีข้อมูลหรือยัง
  $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");

  // ถ้ายังไม่มีข้อมูล หรือ force_refresh = true → ดึงใหม่จาก Google Sheets
  if (!$count || filter_var($atts['force_refresh'], FILTER_VALIDATE_BOOLEAN)) {
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?key={$api_key}";
    $res = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($res)) {
      return '<p class="text-red-600">HTTP Error: ' . esc_html($res->get_error_message()) . '</p>';
    }

    $body = wp_remote_retrieve_body($res);
    $data = json_decode($body, true);
    if (empty($data['values']) || count($data['values']) < 2) {
      return '<p class="text-gray-600">No data found in Google Sheet.</p>';
    }

    // เคลียร์ข้อมูลเก่า
    $wpdb->query("TRUNCATE TABLE $table");

    $headers = array_shift($data['values']);
    $inserted = 0;
    foreach ($data['values'] as $row) {
      $obj = [];
      foreach ($headers as $i => $h)
        $obj[$h] = $row[$i] ?? '';

      $agency = trim($obj['Agency Name'] ?? '');
      if ($agency === '')
        continue;

      $wpdb->insert($table, [
        'agency_name' => $agency,
        'website' => trim($obj['Website'] ?? ''),
        'facebook' => trim($obj['Facebook Page'] ?? ''),
        'phone' => trim($obj['Phone Number'] ?? ''),
        'logo' => trim(($obj['URL Logo'] ?? '') ?: ($obj['Logo URL'] ?? '')),
        'meta_desc' => trim(($obj['Meta Description (EN)'] ?? '') ?: ($obj['Meta Description (TH)'] ?? '')),
        'first_letter' => strtoupper(mb_substr($agency, 0, 1, 'UTF-8')),
        'updated_at' => current_time('mysql'),
      ]);
      $inserted++;
    }
  }

  // 📦 ดึงข้อมูลจากตารางนี้
  $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY first_letter ASC, agency_name ASC", ARRAY_A);
  if (empty($rows)) {
    return '<p class="text-gray-600">No data in this sheet.</p>';
  }

  // ✅ สร้าง HTML พร้อม H3 (สำหรับ TOC)
  $html = '<div class="cgsd-tailwind">';
  $current_letter = null;
  $category_count = 0; // นับจำนวนหมวด

  foreach ($rows as $r) {
    $agency = trim($r['agency_name']);
    $website = trim($r['website']);
    $facebook = trim($r['facebook']);
    $phone = trim($r['phone']);
    $logo = trim($r['logo']);
    $desc = trim($r['meta_desc']);
    $letter = strtoupper($r['first_letter']);
    $initial = mb_substr($agency, 0, 1, 'UTF-8');

    // 🔹 รวมหมวดตัวเลขให้เป็นกลุ่มเดียวกัน (0-9)
    if (preg_match('/^[0-9]/', $letter)) {
      $letter = '0-9';
    }

    if ($letter !== $current_letter) {
      $current_letter = $letter;
      $category_count++; // เพิ่มจำนวนหมวดทุกครั้งที่เจอหมวดใหม่

      $html .= '<h3 class="!text-2xl font-bold mt-2 !mb-1 text-[#0B284D] border-b border-gray-300 !pb-0">'
        . 'รายชื่อ Agency ประเภทหมวด ' . esc_html($letter) . '</h3>';

      // แทรก shortcode หลังจากแสดงหมวดครบทุก 3 หมวด (หลังหมวดที่ 3, 6, 9, ...)
      if ($category_count > 0 && $category_count % 3 === 0 && !empty($cta_template_id)) {
        $html .= do_shortcode('[elementor-template id="' . esc_attr($cta_template_id) . '"]');
      }
    }
    $html .= '
        <article class="relative flex items-stretch rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden mb-4 shadow-sm hover:shadow-md transition-all">
          <div class="flex w-1/3 md:w-[15%] min-w-[110px] bg-[#0B284D] items-center justify-center">
            ' . (
      $logo
      ? '<img src="' . esc_url($logo) . '" loading="lazy" alt="' . esc_attr($agency) . ' logo" class="w-full h-full object-contain" />'
      : '<div class="w-full h-full bg-white/10 text-white flex items-center justify-center font-bold text-xl">'
      . esc_html($initial) .
      '</div>'
    ) . '
          </div>
          <div class="hidden sm:block w-px bg-gray-200"></div>
          <div class="flex-1 px-3 py-[4px] md:py-[7px] text-left">
            <p class="text-[14px] font-bold text-[#0B284D] my-[5px]">' . esc_html($agency) . '</p>
            ' . ($desc ? '<p class="text-[14px] text-gray-900 line-clamp-2 leading-4 h-[35px] overflow-hidden my-0">' . esc_html($desc) . '</p>' : '') . '
            <div class="mt-2 flex flex-wrap items-center gap-x-3 text-sm">
              ' . ($website ? '<div class="flex items-center gap-2"><i class="fa-solid fa-globe text-[#0B284D] text-[14px]"></i><a href="' . esc_url($website) . '" target="_blank" class="underline break-all text-[#0B284D] hover:opacity-80 text-[13px] font-sarabun transition-all md:block hidden">' . esc_html($website) . '</a></div>' : '') . '
              ' . ($facebook ? '<div class="flex items-center gap-2"><i class="fa-brands fa-facebook-f text-[#0B284D] text-[14px]"></i><a href="' . esc_url($facebook) . '" target="_blank" class="underline break-all text-[#0B284D] hover:opacity-80 text-[13px] font-sarabun transition-all md:block hidden">' . esc_html($agency) . '</a></div>' : '') . '
              ' . ($phone ? '<div class="flex items-center gap-2"><i class="fa-solid fa-mobile-screen text-[#173A63] text-[14px]"></i><a href="tel:' . preg_replace('/\D+/', '', $phone) . '" class="underline break-all text-[#0B284D] hover:opacity-80 text-[13px] font-sarabun transition-all md:block hidden">' . esc_html($phone) . '</a></div>' : '') . '
            </div>
          </div>
        </article>';
  }

  $html .= '</div>';
  return $html;
});

/** -----------------------------------------------------------
 * 4.1) AJAX: Save Settings  (sheet_id / range / api_key)
 * ----------------------------------------------------------- */
add_action('wp_ajax_cgsd_save_settings', function () {
  if (!current_user_can('manage_options'))
    wp_send_json_error('Permission', 403);
  check_ajax_referer('cgsd_admin', 'nonce');

  $sheet_id = sanitize_text_field($_POST['sheet_id'] ?? '');
  $range = sanitize_text_field($_POST['range'] ?? '');
  $api_key = sanitize_text_field($_POST['api_key'] ?? '');

  if ($sheet_id)
    update_option('cgsd_sheet_id', $sheet_id);
  if ($range)
    update_option('cgsd_range', $range);
  if ($api_key)
    update_option('cgsd_api_key', $api_key);

  wp_send_json_success('Saved');
});

/** -----------------------------------------------------------
 * 4.2) AJAX: Fetch Google Sheets → DB (ตารางหลัก CGSD_TABLE)
 * ----------------------------------------------------------- */
add_action('wp_ajax_cgsd_fetch_to_db', function () {
  if (!current_user_can('manage_options'))
    wp_send_json_error('Permission', 403);
  check_ajax_referer('cgsd_admin', 'nonce');

  global $wpdb;

  // ใช้ค่าที่ส่งมา; ถ้าไม่ส่ง ให้ดึงจาก options
  $sheet_id = sanitize_text_field($_POST['sheet_id'] ?? get_option('cgsd_sheet_id', ''));
  $range = sanitize_text_field($_POST['range'] ?? get_option('cgsd_range', 'Sheet1!A:H'));
  $api_key = sanitize_text_field($_POST['api_key'] ?? get_option('cgsd_api_key', ''));

  if (!$sheet_id || !$range || !$api_key) {
    wp_send_json_error('Missing sheet_id / range / api_key', 400);
  }

  // ดึงจาก Google Sheets
  $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$range}?key={$api_key}";
  $res = wp_remote_get($url, ['timeout' => 20]);
  if (is_wp_error($res))
    wp_send_json_error($res->get_error_message(), 400);

  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (empty($data['values']) || count($data['values']) < 2) {
    wp_send_json_error('No data found', 400);
  }

  // สร้างตารางหลัก (ถ้ายังไม่มี)
  $charset = $wpdb->get_charset_collate();
  $sql = "CREATE TABLE IF NOT EXISTS " . CGSD_TABLE . "(
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        agency_name VARCHAR(255) DEFAULT '' NOT NULL,
        website TEXT, facebook TEXT, phone VARCHAR(50),
        logo TEXT, meta_desc TEXT, first_letter VARCHAR(8) DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);

  // เคลียร์แล้วใส่ใหม่
  $wpdb->query("TRUNCATE TABLE " . CGSD_TABLE);
  $headers = array_shift($data['values']);
  $inserted = 0;

  foreach ($data['values'] as $row) {
    $obj = [];
    foreach ($headers as $i => $h)
      $obj[$h] = $row[$i] ?? '';

    $agency = trim($obj['Agency Name'] ?? '');
    if ($agency === '')
      continue;

    $wpdb->insert(CGSD_TABLE, [
      'agency_name' => $agency,
      'website' => trim($obj['Website'] ?? ''),
      'facebook' => trim($obj['Facebook Page'] ?? ''),
      'phone' => trim($obj['Phone Number'] ?? ''),
      'logo' => trim(($obj['URL Logo'] ?? '') ?: ($obj['Logo URL'] ?? '')),
      'meta_desc' => trim(($obj['Meta Description (EN)'] ?? '') ?: ($obj['Meta Description (TH)'] ?? '')),
      'first_letter' => strtoupper(mb_substr($agency, 0, 1, 'UTF-8')),
      'updated_at' => current_time('mysql'),
    ]);
    $inserted++;
  }

  wp_send_json_success("Imported {$inserted} rows");
});
