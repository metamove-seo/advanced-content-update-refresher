<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Plugin-Optionen löschen

//delete_option('acur_settings');

// Alle geplanten Cronjobs entfernen
$all_post_types = get_post_types(array('public' => true), 'names');
foreach ($all_post_types as $post_type) {
    $timestamp = wp_next_scheduled("acur_update_{$post_type}");
    if ($timestamp) {
        wp_unschedule_event($timestamp, "acur_update_{$post_type}");
    }
}
