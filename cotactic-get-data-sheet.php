<?php
/**
 * Plugin Name: Cotactic Google Sheets Data Admin Fetch
 * Description: Fetch Google Sheet via admin button, render Tailwind grid, persistent cache until next fetch.
 * Version: 1.1
 */

if (!defined('ABSPATH')) exit;
// ---------- Admin Menu ----------
add_action('admin_menu', function() {
    add_menu_page('CGSD Fetch', 'CGSD Fetch', 'manage_options', 'cgsd-fetch', 'cgsd_admin_page', '', 20);
});

function cgsd_admin_page() {
    $nonce = wp_create_nonce('cgsd_admin_nonce');
    ?>
<div class="wrap">
  <h1>CGSD Fetch Data</h1>

  <!-- Settings form -->
  <form method="post" action="options.php">
    <?php
      settings_fields('cgsd_settings');   // nonce + group
      do_settings_sections('cgsd-fetch'); // fields & section
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
      }, function (response) {
        $('#cgsd-fetch-msg').text(response.success ? response.data : ('Error: ' + response.data));
      });
    });

    $('#cgsd-clear-cache').on('click', function () {
      $('#cgsd-fetch-msg').text('Clearing cache...');
      $.post(ajaxurl, {
        action: 'cgsd_clear_cache',
        _ajax_nonce: '<?php echo esc_js($nonce); ?>'
      }, function (response) {
        $('#cgsd-fetch-msg').text(response.success ? response.data : ('Error: ' + response.data));
      });
    });
  });
</script>
<?php
}

add_action('wp_ajax_cgsd_clear_cache', function() {
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    check_ajax_referer('cgsd_admin_nonce');

    // คุณใช้คีย์คงที่ set_transient('cgsd_sheet_data', ...)
    delete_transient('cgsd_sheet_data');

    // (ทางเลือก) purge page cache หากมีปลั๊กอินแคช
    if (function_exists('rocket_clean_domain')) rocket_clean_domain();
    if (function_exists('litespeed_purge_all')) do_action('litespeed_purge_all');
    if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();

    wp_send_json_success('Cache cleared.');
});

// ---------- AJAX Handler ----------
add_action('wp_ajax_cgsd_fetch_sheet', function() {
    // รับค่า Sheet ID / Range / API Key จาก transient หรือจาก admin JS
    // ในเวอร์ชันนี้ ใช้ค่าที่ shortcode กำหนดหรือค่าตั้งต้น
    $sheet_id = isset($_POST['sheet_id']) ? sanitize_text_field($_POST['sheet_id']) : get_option('cgsd_last_sheet_id', '1Rg1nz4cj38Ut2dIFBRAWIj072nIERoYhSQ_SezzIhLo');
    $range    = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : get_option('cgsd_last_range', '200Digital!A1:H');
    $api_key  = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : get_option('cgsd_last_api_key', 'AIzaSyBqUmwAM8Ubuf7pnpBipJgsSvG9IjxbDlc');

    if (!$sheet_id || !$api_key) {
        wp_send_json_error('Missing Sheet ID or API Key.');
    }
    // Fetch data from Google Sheets API
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


// ---------- Register Settings & Fields ----------
add_action('admin_init', function () {
    // options: cgsd_last_sheet_id, cgsd_last_range, cgsd_last_api_key
    register_setting('cgsd_settings', 'cgsd_last_sheet_id', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('cgsd_settings', 'cgsd_last_range',    ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('cgsd_settings', 'cgsd_last_api_key',  ['sanitize_callback' => 'sanitize_text_field']);

    // สร้าง section บนเพจ slug = cgsd-fetch (เพจเดียวกับเมนูของคุณ)
    add_settings_section('cgsd_section_main', 'Google Sheets Settings', function () {
        echo '<p>กรอกข้อมูลของ Google Sheets ให้ครบ แล้วกด Save Settings จากนั้นจึงกด Fetch Data</p>';
    }, 'cgsd-fetch');

    // Field: Sheet ID
    add_settings_field('cgsd_field_sheet_id', 'Sheet ID', function () {
        $v = esc_attr(get_option('cgsd_last_sheet_id', ''));
        echo '<input name="cgsd_last_sheet_id" type="text" class="regular-text" value="' . $v . '" placeholder="1AbC... (ค่าจาก URL ของชีต)">';
    }, 'cgsd-fetch', 'cgsd_section_main');

    // Field: Range
    add_settings_field('cgsd_field_range', 'Range', function () {
        $v = esc_attr(get_option('cgsd_last_range', 'Sheet5!A:H'));
        echo '<input name="cgsd_last_range" type="text" class="regular-text" value="' . $v . '" placeholder="เช่น Sheet5!A:H">';
    }, 'cgsd-fetch', 'cgsd_section_main');

    // Field: API Key (ซ่อนเป็น password พร้อมปุ่มโชว์)
    add_settings_field('cgsd_field_api_key', 'API Key', function () {
        $v = esc_attr(get_option('cgsd_last_api_key', ''));
        echo '<input name="cgsd_last_api_key" type="password" class="regular-text" value="' . $v . '" placeholder="AIza..."> ';
        echo '<label><input type="checkbox" id="cgsd-show-key"> Show</label>';
        echo '<script>
            jQuery(function($){
                $("#cgsd-show-key").on("change", function(){
                    const i = $("input[name=\'cgsd_last_api_key\']");
                    i.attr("type", this.checked ? "text" : "password");
                });
            });
        </script>';
    }, 'cgsd-fetch', 'cgsd_section_main');
});


// ---------- Shortcode ----------
function cgsd_sheet_shortcode() {
   if (!wp_script_is('jquery', 'enqueued')) wp_enqueue_script('jquery');

    wp_enqueue_style(
        'cgsd-fa',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
        [],
        '6.5.0'
    );

    // wp_enqueue_script('cgsd-tailwind', 'https://cdn.tailwindcss.com', [], null, true);
    wp_enqueue_script('cgsd-js', plugin_dir_url(__FILE__) . 'dist/js/cgsd.js', ['jquery'], '1.1', true);
    wp_enqueue_style('cgsd-css', plugin_dir_url(__FILE__) . 'dist/css/app.css', true);
    // เพิ่ม defer ให้สคริปต์นี้
    add_filter('script_loader_tag', function ($tag, $handle) {
        if ('cgsd-js' === $handle) {
            return str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }, 10, 2);

    wp_localize_script('cgsd-js', 'cgsd_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    $values = get_transient('cgsd_sheet_data');

    if (empty($values) || count($values) < 2) {
        return '<p class="text-yellow-700">No data available. Click fetch in admin.</p>';
    }

    $headers = $values[0];
    $rows = array_slice($values, 1);

    // หา index ของคอลัมน์ที่เป็นชื่อเอเจนซี
    $idxAgency = array_search('Agency Name', $headers);
    if ($idxAgency === false) {
        return '<p class="text-red-700">ไม่พบคอลัมน์ "Agency Name" ในชีต</p>';
    }

    // ✅ จัดเรียงตามชื่อก่อน เพื่อให้หัวข้อหมวดเรียงถูกต้อง
    usort($rows, function($a, $b) use ($idxAgency) {
        $av = isset($a[$idxAgency]) ? $a[$idxAgency] : '';
        $bv = isset($b[$idxAgency]) ? $b[$idxAgency] : '';
        return strcasecmp($av, $bv);
    });

    $html = '<div class="grid grid-cols-1 !gap-5">';
    $current_letter = null;

    foreach ($rows as $r) {
        // map row -> assoc
        $obj = [];
        foreach ($headers as $i => $h) {
            $obj[$h] = isset($r[$i]) ? $r[$i] : '';
        }

        $agency   = trim($obj['Agency Name'] ?? '');
        if ($agency === '') continue; // ข้ามแถวว่างๆ

        $desc     = trim($obj['Meta Description'] ?? ($obj['About'] ?? ''));
        $logo     = trim($obj['URL Logo'] ?? ($obj['Logo URL'] ?? ''));
        $website  = trim($obj['Website'] ?? '');
        $facebook = trim($obj['Facebook Page'] ?? '');
        $phone    = trim($obj['Phone Number'] ?? '');

        // ทำ URL ให้ครบ https:// และ validate
        foreach (['website','facebook'] as $k) {
            if (!empty($$k) && !preg_match('#^https?://#i', $$k)) { $$k = 'https://' . $$k; }
            if (!empty($$k) && !filter_var($$k, FILTER_VALIDATE_URL)) { $$k = ''; }
        }

        // ตัวอักษรแรกของชื่อ (A–Z เท่านั้น, อย่างอื่นเป็น #)
        $first_letter = strtoupper(mb_substr($agency, 0, 1, 'UTF-8'));
        if (!preg_match('/[A-Z]/', $first_letter)) { $first_letter = '0-9'; }

        // ✅ แทรกหัวข้อหมวด เมื่อเจอหมวดใหม่
        if ($first_letter !== $current_letter) {
            $current_letter = $first_letter;
            $html .= '<h2 class="text-2xl font-bold mt-8 mb-2 text-[#0B284D] border-b border-gray-300 pb-1">'
                   . 'ข้อมูลประเภทหมวด ' . esc_html($current_letter) . '</h2>';
        }

        // อักษร fallback บนบล็อกโลโก้
        $initial = mb_strtoupper(mb_substr($agency, 0, 1, 'UTF-8'));

        // ---- Card เดิมของคุณ ----
        $html .= '
        <article class="group hover:shadow-lg transition-all relative flex items-stretch rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden">
            <div class="flex w-1/3 md:w-[30%] min-w-[100px] bg-gradient-to-br from-[#0B284D] to-[#0B284D] items-center justify-center">
                ' . (
                    $logo
                    ? '<img src="' . esc_url($logo) . '" alt="' . esc_attr($agency) . ' logo" class="w-full !h-full object-cover drop-shadow" />'
                    : '<div class="w-full h-full rounded-xl bg-white/10 text-white font-semibold flex items-center justify-center text-xl">'
                        . esc_html($initial) .
                      '</div>'
                ) . '
            </div>

            <div class="hidden sm:block w-px bg-gray-200"></div>

            <div class="flex-1 p-4 md:p-6">
                <h3 class="text-[24px] font-bold text-[#0B284D]">' . esc_html($agency) . '</h3>
                ' . ( $desc ? '<p class="md:mt-2 text-[16px] font-sarabun leading-6 text-gray-900 h-[50px] max-h-[50px] overflow-hidden">' . esc_html($desc) . '</p>' : '' ) . '

                <div class="mt-1 md:mt-4 flex md:flex-wrap items-center gap-x-2 md:gap-x-6 gap-y-3 text-sm">
                    ' . ( $website ? '
                    <div class="flex items-center gap-2">
                        <span class="inline-flex w-7 h-7 items-center justify-center rounded-full text-[#0B284D]">
                            <i class="fa-solid fa-globe text-[18px]" aria-hidden="true"></i>
                            <span class="sr-only">Website</span>
                        </span>
                        <a href="' . esc_url($website) . '" target="_blank" rel="noopener"
                           class="underline break-all text-[#0B284D] hover:opacity-80 text-[16px] font-sarabun transition-all md:block hidden">' . esc_html($website) . '</a>
                    </div>' : '' ) . '

                    ' . ( $facebook ? '
                    <div class="flex items-center gap-2">
                        <span class="inline-flex w-7 h-7 items-center justify-center rounded-full text-[#0B284D]">
                            <i class="fa-brands fa-facebook-f text-[18px]" aria-hidden="true"></i>
                            <span class="sr-only">Facebook</span>
                        </span>
                        <a href="' . esc_url($facebook) . '" target="_blank" rel="noopener"
                           class="underline break-all text-[#0B284D] hover:opacity-80 text-[16px] font-sarabun transition-all md:block hidden">' . esc_html($facebook) . '</a>
                    </div>' : '' ) . '

                    ' . ( $phone ? '
                    <div class="flex items-center gap-2">
                        <span class="inline-flex w-7 h-7 items-center justify-center rounded-md text-[#173A63]">
                            <i class="fa-solid fa-mobile-screen text-[18px]" aria-hidden="true"></i>
                            <span class="sr-only">Phone</span>
                        </span>
                        <a href="tel:' . esc_attr(preg_replace("/\D+/", "", $phone)) . '">
                            <span class="text-[#0B284D] hover:opacity-80 text-[16px] font-sarabun transition-all md:block hidden">' . esc_html($phone) . '</span>
                        </a>
                    </div>' : '' ) . '
                </div>
            </div>
        </article>';
    }

    $html .= '</div>';

    return $html;
}
add_shortcode('google_sheets_data', 'cgsd_sheet_shortcode');
