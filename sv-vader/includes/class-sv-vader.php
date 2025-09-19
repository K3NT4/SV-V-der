<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/providers.php';

class SV_Vader_API {
    private $cache_minutes;

    public function __construct($cache_minutes = 10) {
        $this->cache_minutes = max(1, intval($cache_minutes));
    }

    /**
     * Hämtar konsensus-väder för plats från valda providers.
     * @param string $ort
     * @param string $lat
     * @param string $lon
     * @param array  $providers ['openmeteo','smhi','yr']
     * @param string $yr_contact  (User-Agent kontaktsträng för MET Norway)
     */
    public function get_current_weather($ort = '', $lat = '', $lon = '', $providers = [], $yr_contact = '') {
        $ort = trim((string)$ort);
        $lat = trim((string)$lat);
        $lon = trim((string)$lon);

        $cache_key = 'sv_vader_cons_' . md5(json_encode([$ort,$lat,$lon,$providers]));
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        if ($lat === '' || $lon === '') {
            $coords = $this->geocode($ort);
            if (is_wp_error($coords)) return $coords;
            $lat = $coords['lat'];
            $lon = $coords['lon'];
            $name = $coords['name'];
        } else {
            $name = $ort;
        }

        $samples = [];
        if (in_array('openmeteo', $providers, true)) {
            $om = svp_openmeteo_current($lat, $lon, 'sv');
            if ($om) $samples[] = $om;
        }
        if (in_array('smhi', $providers, true)) {
            $sm = svp_smhi_current($lat, $lon);
            if ($sm) $samples[] = $sm;
        }
        if (in_array('yr', $providers, true)) {
            $yr = svp_yr_current($lat, $lon, $yr_contact);
            if ($yr) $samples[] = $yr;
        }

        if (empty($samples)) {
            return new WP_Error('sv_vader_no_sources', __('Kunde inte hämta väderdata från valda källor.', 'sv-vader'));
        }

        $cons = svp_consensus($samples);
        $out = array_merge([
            'name' => $name ?: $ort,
            'lat'  => $lat,
            'lon'  => $lon,
        ], $cons);

        set_transient($cache_key, $out, MINUTE_IN_SECONDS * $this->cache_minutes);
        return $out;
    }

    /** Geokod via Open-Meteo */
    private function geocode($q) {
        $url = add_query_arg([
            'name' => $q,
            'count' => 1,
            'language' => 'sv',
            'format' => 'json'
        ], 'https://geocoding-api.open-meteo.com/v1/search');

        $res = wp_remote_get($url, ['timeout'=>10]);
        if (is_wp_error($res)) return $res;

        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($data['results'][0])) {
            return new WP_Error('sv_vader_geocode', __('Kunde inte hitta platsen.', 'sv-vader'));
        }

        $r = $data['results'][0];
        return [
            'lat' => (string)$r['latitude'],
            'lon' => (string)$r['longitude'],
            'name' => trim(($r['name'] ?? '') . (isset($r['country_code']) ? ', ' . $r['country_code'] : ''))
        ];
    }

    /** Ikonval baserat på Open-Meteo WMO-kod om sådan finns */
    public function map_icon_url($code) {
        if ($code === null) return '';
        $slug = 'clear-day';
        if (in_array($code, [0,1])) $slug = 'clear-day';
        elseif (in_array($code, [2,3,45,48])) $slug = 'cloudy';
        elseif (in_array($code, [51,53,55,61,63,65,80,81,82])) $slug = 'rain';
        elseif (in_array($code, [71,73,75,85,86,77])) $slug = 'snow';
        elseif (in_array($code, [95,96,99])) $slug = 'thunderstorms';
        return 'https://cdn.jsdelivr.net/gh/erikflowers/weather-icons/svg/wi-' . $slug . '.svg';
    }
}
