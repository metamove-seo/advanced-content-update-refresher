<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Plugin-Optionen löschen

//delete_option('acur_settings');

// Alle geplanten Cronjobs entfernen
$acur_all_post_types = get_post_types(array('public' => true), 'names');

foreach ($acur_all_post_types as $post_type) {
    $acur_timestamp = wp_next_scheduled("acur_update_{$post_type}");
    if ($acur_timestamp) {
        wp_unschedule_event($acur_timestamp, "acur_update_{$post_type}");
    }
}
