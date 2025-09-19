<?php
if (!defined('ABSPATH')) exit;

class SV_Vader_API {
    private $cache_minutes;

    public function __construct($cache_minutes = 10) {
        $this->cache_minutes = max(1, intval($cache_minutes));
    }

    /**
     * Hämtar nuvarande väder från Open-Meteo.
     * Om lat/lon saknas försöker vi geokoda ortsnamnet via Open-Meteo Geocoding (gratis).
     */
    public function get_current_weather($ort = '', $lat = '', $lon = '') {
        $ort = trim((string)$ort);
        $lat = trim((string)$lat);
        $lon = trim((string)$lon);

        $cache_key = 'sv_vader_' . md5(json_encode([$ort,$lat,$lon]));
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        if ($lat === '' || $lon === '') {
            if ($ort === '') {
                return new WP_Error('sv_vader_missing_params', __('Ingen ort eller koordinater angivna.', 'sv-vader'));
            }
            $coords = $this->geocode($ort);
            if (is_wp_error($coords)) return $coords;
            $lat = $coords['lat'];
            $lon = $coords['lon'];
            $name = $coords['name'];
        } else {
            $name = $ort;
        }

        $args = [
            'latitude'  => $lat,
            'longitude' => $lon,
            'current'   => 'temperature_2m,wind_speed_10m,weather_code',
            'timezone'  => 'Europe/Stockholm',
            'lang'      => 'sv'
        ];
        $url = add_query_arg($args, 'https://api.open-meteo.com/v1/forecast');

        $res = wp_remote_get($url, ['timeout'=>10]);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        if ($code !== 200) {
            return new WP_Error('sv_vader_http', sprintf(__('Fel från vädertjänst (%d).', 'sv-vader'), $code));
        }

        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($data['current'])) {
            return new WP_Error('sv_vader_empty', __('Inga väderdata tillgängliga.', 'sv-vader'));
        }

        $curr = $data['current'];
        $out = [
            'name' => $name ?: $ort,
            'lat'  => $lat,
            'lon'  => $lon,
            'temp' => $curr['temperature_2m'] ?? null,
            'wind' => $curr['wind_speed_10m'] ?? null,
            'code' => $curr['weather_code'] ?? null,
            'desc' => $this->code_to_text($curr['weather_code'] ?? null),
        ];

        set_transient($cache_key, $out, MINUTE_IN_SECONDS * $this->cache_minutes);
        return $out;
    }

    /** Enkel geokodning via Open-Meteo */
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

    /** Mappa Open-Meteo weather_code till enkel svensk text */
    public function code_to_text($code) {
        $map = [
            0=>'Klart', 1=>'Mest klart', 2=>'Växlande molnighet', 3=>'Mulet',
            45=>'Dimma', 48=>'Dimfrost',
            51=>'Duggregn svagt', 53=>'Duggregn måttligt', 55=>'Duggregn kraftigt',
            61=>'Regn svagt', 63=>'Regn måttligt', 65=>'Regn kraftigt',
            66=>'Underkylt regn svagt', 67=>'Underkylt regn kraftigt',
            71=>'Snöfall svagt', 73=>'Snöfall måttligt', 75=>'Snöfall kraftigt',
            77=>'Kornsnö',
            80=>'Skurar svaga', 81=>'Skurar måttliga', 82=>'Skurar kraftiga',
            85=>'Snöbyar svaga', 86=>'Snöbyar kraftiga',
            95=>'Åska', 96=>'Åska med svaga hagel', 99=>'Åska med kraftiga hagel'
        ];
        return $map[$code] ?? '';
    }

    /** Ikoner (enkla SVG från open-meteo ikoner, här proxade via jsDelivr för demo) */
    public function map_icon_url($code) {
        if ($code === null) return '';
        $slug = 'clear-day';
        // Minimal mappning för demo
        if (in_array($code, [0,1])) $slug = 'clear-day';
        elseif (in_array($code, [2,3,45,48])) $slug = 'cloudy';
        elseif (in_array($code, [51,53,55,61,63,65,80,81,82])) $slug = 'rain';
        elseif (in_array($code, [71,73,75,85,86,77])) $slug = 'snow';
        elseif (in_array($code, [95,96,99])) $slug = 'thunderstorms';

        // Fri ikonuppsättning; byt gärna till egna assets
        return 'https://cdn.jsdelivr.net/gh/erikflowers/weather-icons/svg/wi-' . $slug . '.svg';
    }
}
