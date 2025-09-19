<?php
if (!defined('ABSPATH')) exit;

function sv_vader_register_settings_page() {
    add_options_page(
        __('SV Väder', 'sv-vader'),
        __('SV Väder', 'sv-vader'),
        'manage_options',
        'sv-vader',
        'sv_vader_render_settings_page'
    );

    register_setting('sv_vader_group', 'sv_vader_options', [
        'type' => 'array',
        'sanitize_callback' => 'sv_vader_sanitize_options',
        'default' => [
            'default_ort' => 'Stockholm',
            'cache_minutes' => 10,
            'show_wind' => 1,
        ],
    ]);

    add_settings_section('sv_vader_main', __('Standardinställningar', 'sv-vader'), '__return_false', 'sv_vader');

    add_settings_field('default_ort', __('Standardort', 'sv-vader'), function(){
        $o = get_option('sv_vader_options');
        printf('<input type="text" name="sv_vader_options[default_ort]" value="%s" class="regular-text" />',
            esc_attr($o['default_ort'] ?? 'Stockholm'));
    }, 'sv_vader', 'sv_vader_main');

    add_settings_field('cache_minutes', __('Cache (minuter)', 'sv-vader'), function(){
        $o = get_option('sv_vader_options');
        printf('<input type="number" min="1" name="sv_vader_options[cache_minutes]" value="%d" class="small-text" />',
            intval($o['cache_minutes'] ?? 10));
    }, 'sv_vader', 'sv_vader_main');

    add_settings_field('show_wind', __('Visa vind som standard', 'sv-vader'), function(){
        $o = get_option('sv_vader_options');
        printf('<label><input type="checkbox" name="sv_vader_options[show_wind]" value="1" %s /> %s</label>',
            checked(1, intval($o['show_wind'] ?? 1), false),
            esc_html__('Ja', 'sv-vader')
        );
    }, 'sv_vader', 'sv_vader_main');
}

function sv_vader_render_settings_page() {
    ?>
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
        <h2><?php esc_html_e('Användning', 'sv-vader'); ?></h2>
        <p><code>[sv_vader ort="Göteborg"]</code></p>
        <p><code>[sv_vader lat="57.7089" lon="11.9746" ort="Göteborg" show="temp,wind,icon"]</code></p>
        <p><?php esc_html_e('Blocket “SV Väder” finns i blockinsättaren (Widgets-kategori).', 'sv-vader'); ?></p>
    </div>
    <?php
}

function sv_vader_sanitize_options($in) {
    return [
        'default_ort' => sanitize_text_field($in['default_ort'] ?? 'Stockholm'),
        'cache_minutes' => max(1, intval($in['cache_minutes'] ?? 10)),
        'show_wind' => isset($in['show_wind']) ? 1 : 0,
    ];
}
