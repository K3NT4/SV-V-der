<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

delete_option('sv_vader_options');

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sv_vader_%' OR option_name LIKE '_transient_timeout_sv_vader_%'" );
