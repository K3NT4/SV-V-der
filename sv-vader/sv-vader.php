<?php
/**
 * Plugin Name: SV Väder
 * Description: Visar aktuellt väder för vald plats (Open-Meteo). Kortkod [sv_vader] och Gutenberg-block.
 * Version: 1.3.2
 * Author: Nyttodata Väst AB
 * Text Domain: sv-vader
 * Requires at least: 6.0.0
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;

// ── Vakta konstanter (tål dubbelinladdning under utveckling)
if (!defined('SV_VADER_VER')) define('SV_VADER_VER', '1.3.2');
if (!defined('SV_VADER_DIR')) define('SV_VADER_DIR', plugin_dir_path(__FILE__));
if (!defined('SV_VADER_URL')) define('SV_VADER_URL', plugin_dir_url(__FILE__));
// Låst attribution – ODbL-krav
if (!defined('SV_VADER_ATTRIB_HTML')) {
    define('SV_VADER_ATTRIB_HTML', '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors');
}

// Inkludera en gång per request
require_once SV_VADER_DIR . 'includes/class-sv-vader.php';
require_once SV_VADER_DIR . 'includes/admin-page.php';

// ── Starta pluginet endast om klassen inte redan finns
if (!class_exists('SV_Vader_Plugin')) {
    final class SV_Vader_Plugin {
        public function __construct() {
            add_action('init',               [$this, 'register_shortcodes']);
            add_action('init',               [$this, 'register_block']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
            add_action('plugins_loaded',     [$this, 'load_textdomain']);

            // Admin
            add_action('admin_menu',         'sv_vader_register_admin_menu');
            add_action('admin_init',         'sv_vader_register_settings');

            // Länk på pluginsidan
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        }

        public function load_textdomain() {
            load_plugin_textdomain('sv-vader', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        public function enqueue_public_assets() {
            wp_enqueue_style('sv-vader-style', SV_VADER_URL . 'assets/style.css', [], SV_VADER_VER);

            // Leaflet
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
            wp_enqueue_script('sv-vader-map', SV_VADER_URL . 'assets/map.js', ['leaflet-js'], SV_VADER_VER, true);
        }

        public function register_shortcodes() {
            add_shortcode('sv_vader', [$this, 'render_shortcode']);
        }

        public function render_shortcode($atts = []) {
            $opts = sv_vader_get_options();

            $a = shortcode_atts([
                'ort'        => $opts['default_ort'],
                'lat'        => '',
                'lon'        => '',
                'show'       => $opts['default_show'], // "temp,wind,icon"
                'class'      => '',
                'map'        => $opts['map_default'] ? '1' : '0',
                'map_height' => (string) $opts['map_height'],
                'providers'  => implode(',', array_keys(array_filter([
                    'openmeteo' => $opts['prov_openmeteo'],
                    'smhi'      => $opts['prov_smhi'],
                    'yr'        => $opts['prov_yr'],
                ]))),
            ], $atts, 'sv_vader');

            $provider_list = array_filter(array_map('trim', explode(',', strtolower($a['providers']))));
            $allowed = ['openmeteo','smhi','yr'];
            $provider_list = array_values(array_intersect($provider_list, $allowed));
            if (empty($provider_list)) $provider_list = ['openmeteo'];

            $show = array_map('trim', explode(',', strtolower($a['show'])));

            $api = new SV_Vader_API(intval($opts['cache_minutes']));
            $res = $api->get_current_weather($a['ort'], $a['lat'], $a['lon'], $provider_list, $opts['yr_contact']);
            if (is_wp_error($res)) return '<em>' . esc_html($res->get_error_message()) . '</em>';

            $temp     = isset($res['temp']) ? round($res['temp']) : null;
            $wind     = isset($res['wind']) ? round($res['wind']) : null;
            $icon_url = $api->map_icon_url($res['code'] ?? null);
            $name     = $res['name'];
            $lat      = $res['lat'];
            $lon      = $res['lon'];

            ob_start(); ?>
            <div class="sv-vader <?php echo esc_attr($a['class']); ?>">
                <?php if (!empty($name)): ?>
                    <div class="svv-ort"><?php echo esc_html($name); ?></div>
                <?php endif; ?>

                <div class="svv-row">
                    <?php if (in_array('icon', $show, true) && $icon_url): ?>
                        <img class="svv-icon" src="<?php echo esc_url($icon_url); ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <?php if (in_array('temp', $show, true) && $temp !== null): ?>
                        <div class="svv-temp"><?php echo esc_html($temp); ?>°C</div>
                    <?php endif; ?>
                </div>

                <div class="svv-meta">
                    <?php if (in_array('wind', $show, true) && $wind !== null): ?>
                        <span class="svv-wind"><?php echo esc_html(sprintf(__('Vind: %s m/s', 'sv-vader'), $wind)); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($res['desc'])): ?>
                        <span class="svv-desc"><?php echo esc_html($res['desc']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($a['map'] === '1'): ?>
                    <div class="svv-map"
                         data-lat="<?php echo esc_attr($lat); ?>"
                         data-lon="<?php echo esc_attr($lon); ?>"
                         data-name="<?php echo esc_attr($name); ?>"
                         style="height: <?php echo intval($a['map_height']); ?>px;"></div>

                    <div class="svv-map-attrib"><?php echo wp_kses_post(SV_VADER_ATTRIB_HTML); ?></div>

                    <div class="svv-map-link">
                        <a href="<?php echo esc_url('https://www.openstreetmap.org/?mlat=' . rawurlencode($lat) . '&mlon=' . rawurlencode($lon) . '#map=12/' . rawurlencode($lat) . '/' . rawurlencode($lon)); ?>"
                           target="_blank" rel="noopener"><?php esc_html_e('Visa på OpenStreetMap', 'sv-vader'); ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        public function register_block() {
            register_block_type('sv/vader', [
                'api_version' => 2,
                'render_callback' => function($attrs) {
                    $opts = sv_vader_get_options();
                    $atts = [
                        'ort'        => $attrs['ort'] ?? $opts['default_ort'],
                        'lat'        => $attrs['lat'] ?? '',
                        'lon'        => $attrs['lon'] ?? '',
                        'show'       => $attrs['show'] ?? $opts['default_show'],
                        'class'      => 'is-block',
                        'map'        => !empty($attrs['map']) ? '1' : ($opts['map_default'] ? '1' : '0'),
                        'map_height' => isset($attrs['mapHeight']) ? (string)intval($attrs['mapHeight']) : (string)$opts['map_height'],
                    ];
                    return $this->render_shortcode($atts);
                },
                'attributes' => [
                    'ort'       => ['type' => 'string', 'default' => 'Stockholm'],
                    'lat'       => ['type' => 'string', 'default' => ''],
                    'lon'       => ['type' => 'string', 'default' => ''],
                    'show'      => ['type' => 'string', 'default' => 'temp,wind,icon'],
                    'map'       => ['type' => 'boolean', 'default' => false],
                    'mapHeight' => ['type' => 'number',  'default' => 240],
                ],
                'style' => 'sv-vader-style',
                'title' => __('SV Väder', 'sv-vader'),
                'description' => __('Visar aktuellt väder med karta och konsensus från flera källor.', 'sv-vader'),
                'category' => 'widgets',
                'icon' => 'cloud',
                'keywords' => ['väder', 'weather', 'karta'],
            ]);
        }

        public function plugin_action_links($links) {
            $url = admin_url('admin.php?page=sv-vader');
            $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Inställningar', 'sv-vader') . '</a>';
            return $links;
        }
    }
    new SV_Vader_Plugin();
}