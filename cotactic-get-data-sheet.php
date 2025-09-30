<?php
/**
 * Plugin Name: Cotactic Google Sheets Data
 * Description: Fetch Google Sheet and render as Tailwind grid with grouping. Shortcode: [google_sheets_data sheet_id="..." range="Sheet1!A1:H" group_by="Category" tailwind_cdn="1" cache_ttl="300" limit=""]
 * Version: 1.3
 */

if ( ! defined('ABSPATH') ) exit;

/** ============== Service (memoize per-request) ============== */
function cgsd_get_sheets_service($cred_json) {
    static $service = null;
    if ($service) return $service;

    $client = new Google_Client();
    $client->setApplicationName('Google Sheets Data Fetcher');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
    $client->setAuthConfig($cred_json);
    $client->setAccessType('offline');

    $service = new Google_Service_Sheets($client);
    return $service;
}

/** ============== Cached values (memoize + transient) ============== */
function cgsd_get_values_cached($service, $sheet_id, $range, $ttl = 300) {
    static $memo = [];
    $cache_key = 'cgsd_' . md5($sheet_id . '|' . $range);

    if (isset($memo[$cache_key])) return $memo[$cache_key];

    $cached = get_transient($cache_key);
    if ($cached !== false) {
        $memo[$cache_key] = $cached;
        return $cached;
    }

    $resp   = $service->spreadsheets_values->get($sheet_id, $range, [
        'valueRenderOption' => 'UNFORMATTED_VALUE',
    ]);
    $values = $resp->getValues();

    set_transient($cache_key, $values, max(0, (int)$ttl));
    $memo[$cache_key] = $values;
    return $values;
}

/** ============== Shortcode main ============== */
function cgsd_render_sheet_as_tailwind_cards( $atts ) {
    $atts = shortcode_atts([
        'sheet_id'     => '1XRp8JMgl-B0gB8kTgfdtNFATmgGIJWRMMKgcOjVsclQ',
        'range'        => 'Raw!A1:H',   // row 1 = headers
        // 'group_by'     => 'Category',   // default group key
        // 'limit'        => '',           // limit rows (not include header)
        'cache_ttl'    => '300',        // seconds (0 = no cache)
        'tailwind_cdn' => '1',          // 1=inject CDN, 0=no
    ], $atts, 'google_sheets_data');

    ob_start();

    // Tailwind (guard double-load)
    if ($atts['tailwind_cdn'] === '1') {
        static $tw_once = false;
        if (!$tw_once) {
            $tw_once = true;
            echo '<script src="https://cdn.tailwindcss.com"></script>';
        }
    }

    // Paths
    $plugin_base = plugin_dir_path(__FILE__);
    $autoload    = $plugin_base . 'vendor/autoload.php';
    $cred_path   = $plugin_base . 'credentials.json';

    if ( ! file_exists($autoload) ) {
        echo '<div class="container mx-auto p-4 text-red-700">[CGSD] Missing <code>vendor/autoload.php</code>. Run <code>composer require google/apiclient:^2.15</code></div>';
        return ob_get_clean();
    }
    require_once $autoload;

    if ( ! file_exists($cred_path) ) {
        echo '<div class="container mx-auto p-4 text-red-700">[CGSD] Missing <code>credentials.json</code> (Service Account)</div>';
        return ob_get_clean();
    }

    // Validate credentials.json
    $cred_raw  = file_get_contents($cred_path);
    $cred_json = json_decode($cred_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo '<div class="container mx-auto p-4 text-red-700">[CGSD] credentials.json invalid JSON: '. esc_html(json_last_error_msg()) .'</div>';
        return ob_get_clean();
    }
    if (empty($cred_json['type']) || $cred_json['type'] !== 'service_account') {
        echo '<div class="container mx-auto p-4 text-red-700">[CGSD] credentials.json must be a Service Account key</div>';
        return ob_get_clean();
    }

    try {
        // ===== use memoized service + cached values =====
        $service = cgsd_get_sheets_service($cred_json);
        $values  = cgsd_get_values_cached($service, $atts['sheet_id'], $atts['range'], (int)$atts['cache_ttl']);

        echo '<div class="container mx-auto p-4">';
        if (empty($values) || count($values) < 2) {
            echo '<div class="rounded-xl bg-yellow-50 text-yellow-800 p-4 ring-1 ring-yellow-100">[CGSD] No data found (need headers in first row).</div>';
            echo '</div>';
            return ob_get_clean();
        }

        // Headers
        $headers = array_map(function($h){ return trim((string)$h); }, $values[0]);
        $rows = array_slice($values, 1);
        // if (!empty($atts['limit']) && is_numeric($atts['limit'])) {
        //     $rows = array_slice($rows, 0, (int)$atts['limit']);
        // }

        // Group by
        // $group_by = trim((string)$atts['group_by']);
        // if ($group_by === '' || !in_array($group_by, $headers, true)) {
        //     $group_by = $headers[0]; // fallback to first column
        // }

        $groups = [];
        foreach ($rows as $r) {
            if (count($r) < count($headers)) $r = array_pad($r, count($headers), '');
            $assoc = [];
            foreach ($headers as $i => $h) $assoc[$h] = isset($r[$i]) ? $r[$i] : '';

            $group_val = isset($assoc[$group_by]) && $assoc[$group_by] !== '' ? (string)$assoc[$group_by] : 'Uncategorized';
            $groups[$group_val][] = $assoc;
        }

        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        // URL pattern
        $url_pattern = '/^(https?:\/\/)?([\w.-]+)\.([a-zA-Z]{2,})([\w\/\.\-\?\=\&\#\%]*)?$/';

        foreach ($groups as $g => $items) {
            echo '<section class="mb-10">';
            // grid
            echo '  <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-6">';

            foreach ($items as $assoc) {
                $nameAgen = isset($assoc['Agency Name'])   ? (string)$assoc['Agency Name']   : '';
                $phone    = isset($assoc['Phone Number'])  ? (string)$assoc['Phone Number']  : '';
                $website  = isset($assoc['Website'])       ? (string)$assoc['Website']       : '';
                $facebook = isset($assoc['Facebook Page']) ? (string)$assoc['Facebook Page'] : '';

                echo '<article class="group relative rounded-2xl ring-1 ring-gray-200 bg-white hover:shadow-xl transition-shadow">';
                echo '<div class="col-span-1 font-semibold bg-[#0B284D] py-1 text-center rounded-t-2xl text-[#FED312] text-xl">'. esc_html($nameAgen ?: '—') .'</div>';

                echo '<dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-700 p-4">';
                echo '<div class="col-span-2 font-normal text-sm text-gray-900"><strong>Website</strong> : '. esc_html($website) .'</div>';
                echo '<div class="col-span-2 font-normal text-sm text-gray-900"><strong>Facebook</strong> : '. esc_html($facebook) .'</div>';
                echo '<div class="col-span-2 font-normal text-sm text-gray-900"><strong>Phone</strong> : '. esc_html($phone) .'</div>';
                echo '</dl>';

                echo '<div class="flex items-center justify-center pb-2 gap-3">';

                // Website button
                if (!empty($website) && preg_match($url_pattern, $website)) {
                    echo '<a href="'. esc_url($website) .'" target="_blank" rel="noopener" class="inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-[#0B284D]/90 hover:text-[#FED312] bg-[#0B284D] text-[#FED312]">View</a>';
                }
                // Facebook button
                if (!empty($facebook) && preg_match($url_pattern, $facebook)) {
                    echo '<a href="'. esc_url($facebook) .'" target="_blank" rel="noopener" class="inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-gray-50">Facebook</a>';
                }

                echo '</div>';
                echo '</article>';
            }

            echo '  </div>';
            echo '</section>';
        }

        echo '</div>'; // container

    } catch (Exception $e) {
        echo '<div class="container mx-auto p-4 text-red-700">[CGSD] Error: '.  esc_html($e->getMessage()) .'</div>';
    }

    return ob_get_clean();
}
add_shortcode('google_sheets_data', 'cgsd_render_sheet_as_tailwind_cards');
