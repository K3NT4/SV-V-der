<?php
if (!defined('ABSPATH')) exit;

/**
 * Normaliserat resultat:
 * [ 'temp' => float|null, 'wind' => float|null, 'precip' => float|null, 'cloud' => int|null (0-100), 'code' => int|null, 'desc' => string ]
 * SI-enheter: °C, m/s, mm, %.
 */

/** Open-Meteo – gratis, utan API-nyckel */
function svp_openmeteo_current($lat, $lon, $locale = 'sv') {
    $url = add_query_arg([
        'latitude'  => $lat,
        'longitude' => $lon,
        'current'   => 'temperature_2m,wind_speed_10m,weather_code,precipitation,cloud_cover',
        'timezone'  => 'Europe/Stockholm',
        'lang'      => $locale
    ], 'https://api.open-meteo.com/v1/forecast');

    $res = wp_remote_get($url, ['timeout'=>10]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) return null;
    $j = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($j['current'])) return null;
    $c = $j['current'];
    return [
        'temp'   => isset($c['temperature_2m']) ? floatval($c['temperature_2m']) : null,
        'wind'   => isset($c['wind_speed_10m']) ? floatval($c['wind_speed_10m']) : null,
        'precip' => isset($c['precipitation']) ? floatval($c['precipitation']) : null,
        'cloud'  => isset($c['cloud_cover']) ? intval($c['cloud_cover']) : null,
        'code'   => isset($c['weather_code']) ? intval($c['weather_code']) : null,
        'desc'   => null,
    ];
}

/** SMHI – Punktprognos v2 */
function svp_smhi_current($lat, $lon) {
    $url = sprintf(
        'https://opendata.smhi.se/meteorological/forecast/api/category/pmp3g/version/2/geotype/point/lon/%s/lat/%s/data.json',
        rawurlencode($lon), rawurlencode($lat)
    );
    $res = wp_remote_get($url, ['timeout'=>12, 'user-agent'=>'SV-Vader/1.0 (+https://example.com)']);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) return null;
    $j = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($j['timeSeries'][0])) return null;

    $now = current_time('timestamp', true);
    $nearest = null; $mindiff = PHP_INT_MAX;
    foreach ($j['timeSeries'] as $ts) {
        $t = strtotime($ts['validTime']);
        $diff = abs($t - $now);
        if ($diff < $mindiff) { $mindiff = $diff; $nearest = $ts; }
    }
    if (!$nearest || empty($nearest['parameters'])) return null;

    $map = [];
    foreach ($nearest['parameters'] as $p) {
        $map[$p['name']] = $p['values'][0];
    }
    $cloud_pct = isset($map['tcc']) ? intval(round(($map['tcc'] / 8) * 100)) : null;

    return [
        'temp'   => isset($map['t']) ? floatval($map['t']) : null,
        'wind'   => isset($map['ws']) ? floatval($map['ws']) : null,
        'precip' => isset($map['pmean']) ? floatval($map['pmean']) : null,
        'cloud'  => $cloud_pct,
        'code'   => null,
        'desc'   => null,
    ];
}

/** Yr (MET Norway) – Locationforecast compact */
function svp_yr_current($lat, $lon, $contactUA = '') {
    $ua = 'SV-Vader/1.0';
    if ($contactUA) $ua .= ' (' . $contactUA . ')';

    $url = add_query_arg([
        'lat' => $lat,
        'lon' => $lon,
    ], 'https://api.met.no/weatherapi/locationforecast/2.0/compact');

    $res = wp_remote_get($url, [
        'timeout' => 12,
        'headers' => ['User-Agent' => $ua]
    ]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) return null;
    $j = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($j['properties']['timeseries'][0])) return null;

    $now = current_time('timestamp', true);
    $nearest = null; $mindiff = PHP_INT_MAX;
    foreach ($j['properties']['timeseries'] as $ts) {
        $t = strtotime($ts['time']);
        $diff = abs($t - $now);
        if ($diff < $mindiff) { $mindiff = $diff; $nearest = $ts; }
    }
    if (!$nearest) return null;

    $inst = $nearest['data']['instant']['details'] ?? [];
    $next1h = $nearest['data']['next_1_hours']['details'] ?? [];

    return [
        'temp'   => isset($inst['air_temperature']) ? floatval($inst['air_temperature']) : null,
        'wind'   => isset($inst['wind_speed']) ? floatval($inst['wind_speed']) : null,
        'precip' => isset($next1h['precipitation_amount']) ? floatval($next1h['precipitation_amount']) : null,
        'cloud'  => isset($inst['cloud_area_fraction']) ? intval(round($inst['cloud_area_fraction'])) : null,
        'code'   => null,
        'desc'   => null,
    ];
}

/** Svensk text från WMO-kod (Open-Meteo) */
function svp_wmo_text_sv($code) {
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
        95=>'Åska', 96=>'Åska (svag hagel)', 99=>'Åska (kraftig hagel)'
    ];
    return $map[$code] ?? '';
}

/** Konsensus: median för numeriska, kod/text från Open-Meteo om tillgänglig, annars heuristik */
function svp_consensus(array $samples) {
    $nums = ['temp','wind','precip','cloud'];
    $out = [];
    foreach ($nums as $k) {
        $vals = array_values(array_filter(array_map(function($s) use ($k){ return $s[$k] ?? null; }, $samples), function($v){ return $v !== null; }));
        if ($vals) {
            sort($vals, SORT_NUMERIC);
            $mid = (int) floor((count($vals)-1)/2);
            $out[$k] = $vals[$mid]; // median
        } else {
            $out[$k] = null;
        }
    }

    // Beskrivning/ikon
    $om = null;
    foreach ($samples as $s) { if (isset($s['code']) && $s['code'] !== null) { $om = $s['code']; break; } }
    if ($om !== null) {
        $out['code'] = $om;
        $out['desc'] = svp_wmo_text_sv($om);
    } else {
        $cloud = $out['cloud'];
        $prec  = $out['precip'];
        if ($prec !== null && $prec >= 0.1) {
            $out['desc'] = 'Nederbörd';
        } elseif ($cloud !== null) {
            if ($cloud <= 20)      $out['desc'] = 'Klart';
            elseif ($cloud <= 60)  $out['desc'] = 'Växlande molnighet';
            else                   $out['desc'] = 'Mulet';
        } else {
            $out['desc'] = '';
        }
        $out['code'] = null;
    }
    return $out;
}
