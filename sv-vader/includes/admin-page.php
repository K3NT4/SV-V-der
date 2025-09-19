<?php
if (!defined('ABSPATH')) exit;

// ── Vakta funktionsnamn
if (!function_exists('sv_vader_default_options')) {
    function sv_vader_default_options() : array {
        return [
            'default_ort'   => 'Stockholm',
            'cache_minutes' => 10,
            'default_show'  => 'temp,wind,icon',
            'map_default'   => 1,
            'map_height'    => 240,
            // Attribution låst via SV_VADER_ATTRIB_HTML
            'prov_openmeteo'=> 1,
            'prov_smhi'     => 1,
            'prov_yr'       => 1,
            'yr_contact'    => 'kontakt@example.com',
        ];
    }
}
if (!function_exists('sv_vader_get_options')) {
    function sv_vader_get_options() : array {
        $o = get_option('sv_vader_options', []);
        return wp_parse_args($o, sv_vader_default_options());
    }
}
if (!function_exists('sv_vader_register_admin_menu')) {
    function sv_vader_register_admin_menu() {
        add_menu_page(
            __('SV Väder', 'sv-vader'),
            __('SV Väder', 'sv-vader'),
            'manage_options',
            'sv-vader',
            'sv_vader_render_settings_page',
            'dashicons-cloud',
            65
        );
    }
}
if (!function_exists('sv_vader_register_settings')) {
    function sv_vader_register_settings() {
        register_setting('sv_vader_group', 'sv_vader_options', [
            'type'              => 'array',
            'sanitize_callback' => 'sv_vader_sanitize_options',
            'default'           => sv_vader_default_options(),
            'show_in_rest'      => false,
        ]);

        add_settings_section('sv_vader_main', __('Standardinställningar', 'sv-vader'), '__return_false', 'sv_vader');

        add_settings_field('default_ort', __('Standardort', 'sv-vader'), function(){
            $o = sv_vader_get_options();
            printf('<input type="text" name="sv_vader_options[default_ort]" value="%s" class="regular-text" placeholder="Ex. Stockholm" />',
                esc_attr($o['default_ort']));
        }, 'sv_vader', 'sv_vader_main');

        add_settings_field('cache_minutes', __('Cachetid (minuter)', 'sv-vader'), function(){
            $o = sv_vader_get_options();
            printf('<input type="number" min="1" name="sv_vader_options[cache_minutes]" value="%d" class="small-text" />',
                intval($o['cache_minutes']));
            echo '<p class="description">' . esc_html__('Hur länge väderdata cachas (transients).', 'sv-vader') . '</p>';
        }, 'sv_vader', 'sv_vader_main');

        add_settings_field('default_show', __('Standardvisning', 'sv-vader'), function(){
            $o = sv_vader_get_options();
            printf('<input type="text" name="sv_vader_options[default_show]" value="%s" class="regular-text" />',
                esc_attr($o['default_show']));
            echo '<p class="description">' . esc_html__('Kommaseparerat: temp,wind,icon', 'sv-vader') . '</p>';
        }, 'sv_vader', 'sv_vader_main');

        add_settings_field('map_default', __('Visa karta som standard', 'sv-vader'), function(){
            $o = sv_vader_get_options();
            printf('<label><input type="checkbox" name="sv_vader_options[map_default]" value="1" %s/> %s</label>',
                checked(1, intval($o['map_default']), false),
                esc_html__('Aktivera karta som förval.', 'sv-vader'));
        }, 'sv_vader', 'sv_vader_main');

        add_settings_field('map_height', __('Karthöjd (px)', 'sv-vader'), function(){
            $o = sv_vader_get_options();
            printf('<input type="number" min="120" name="sv_vader_options[map_height]" value="%d" class="small-text" />',
                intval($o['map_height']));
        }, 'sv_vader', 'sv_vader_main');

        // Datakällor
        add_settings_field('providers', __('Datakällor', 'sv-vader'), function(){
            $o = sv_vader_get_options();
            echo '<label><input type="checkbox" name="sv_vader_options[prov_openmeteo]" value="1" '.checked(1,$o['prov_openmeteo'],false).'/> Open-Meteo</label><br>';
            echo '<label><input type="checkbox" name="sv_vader_options[prov_smhi]" value="1" '.checked(1,$o['prov_smhi'],false).'/> SMHI</label><br>';
            echo '<label><input type="checkbox" name="sv_vader_options[prov_yr]" value="1" '.checked(1,$o['prov_yr'],false).'/> Yr (MET Norway)</label>';
        }, 'sv_vader', 'sv_vader_main');

        add_settings_field('yr_contact', __('Yr kontakt/UA', 'sv-vader'), function(){
            $o = sv_vader_get_options();
            printf('<input type="text" name="sv_vader_options[yr_contact]" value="%s" class="regular-text" />',
                esc_attr($o['yr_contact']));
            echo '<p class="description">'.esc_html__('Rekommenderat av MET Norway: e-post eller URL i User-Agent.', 'sv-vader').'</p>';
        }, 'sv_vader', 'sv_vader_main');
    }
}
if (!function_exists('sv_vader_render_settings_page')) {
    function sv_vader_render_settings_page() {
        if (!current_user_can('manage_options')) return; ?>
        <div class="wrap">
            <h1><?php esc_html_e('SV Väder – inställningar', 'sv-vader'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('sv_vader_group');
                do_settings_sections('sv_vader');
                submit_button();
                ?>
            </form>
            <hr>
            <h2><?php esc_html_e('Attribution', 'sv-vader'); ?></h2>
            <p><?php echo wp_kses_post(SV_VADER_ATTRIB_HTML); ?></p>
            <p class="description"><?php esc_html_e('Attributionen är låst för att följa OpenStreetMap/ODbL-kraven.', 'sv-vader'); ?></p>

            <hr>
            <h2><?php esc_html_e('Användning', 'sv-vader'); ?></h2>
            <p><code>[sv_vader ort="Göteborg"]</code></p>
            <p><code>[sv_vader lat="57.7089" lon="11.9746" ort="Göteborg" map="1" map_height="260" show="temp,wind,icon" providers="smhi,yr,openmeteo"]</code></p>
            <p><?php esc_html_e('Blocket “SV Väder” finns i blockinsättaren (Widgets-kategori).', 'sv-vader'); ?></p>
        </div>
        <?php
    }
}
if (!function_exists('sv_vader_sanitize_options')) {
    function sv_vader_sanitize_options($in) : array {
        $def = sv_vader_default_options();
        $out = [];

        $out['default_ort']   = sanitize_text_field($in['default_ort'] ?? $def['default_ort']);
        $out['cache_minutes'] = max(1, intval($in['cache_minutes'] ?? $def['cache_minutes']));

        // visa: endast temp,wind,icon i unik ordning
        $allowed = ['temp','wind','icon'];
        $show_in = strtolower((string)($in['default_show'] ?? $def['default_show']));
        $show_in = array_filter(array_map('trim', explode(',', $show_in)));
        $show_in = array_values(array_unique(array_intersect($show_in, $allowed)));
        $out['default_show'] = implode(',', $show_in ?: ['temp','wind','icon']);

        $out['map_default'] = !empty($in['map_default']) ? 1 : 0;
        $out['map_height']  = max(120, intval($in['map_height'] ?? $def['map_height']));

        // providers
        $out['prov_openmeteo'] = !empty($in['prov_openmeteo']) ? 1 : 0;
        $out['prov_smhi']      = !empty($in['prov_smhi']) ? 1 : 0;
        $out['prov_yr']        = !empty($in['prov_yr']) ? 1 : 0;
        $out['yr_contact']     = sanitize_text_field($in['yr_contact'] ?? $def['yr_contact']);

        return $out;
    }
}
