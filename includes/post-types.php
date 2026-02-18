<?php
if (!defined('ABSPATH')) {
    exit;
}

function acur_get_all_post_types() {
    $args = array(
        'public'   => true,
        '_builtin' => false,
    );
    $custom_post_types = get_post_types($args, 'names');

    // Standard-Posttypen hinzufügen
    $default_post_types = array('post', 'page');
    $all_post_types = array_merge($default_post_types, $custom_post_types);

    // Prüfen, ob SEO-Plugins installiert sind und Post-Typen deaktiviert wurden
    $disabled_post_types = array();

    // Yoast SEO
    if (class_exists('WPSEO_Options')) {
        $wpseo_options = get_option('wpseo_titles');
        if (!empty($wpseo_options['post_types'])) {
            foreach ($wpseo_options['post_types'] as $post_type => $settings) {
                if (isset($settings['noindex']) && $settings['noindex']) {
                    $disabled_post_types[] = $post_type;
                }
            }
        }
    }

    // Rank Math SEO
    if (class_exists('RankMath')) {
        $rankmath_options = get_option('rank-math-options-titles');
        if (!empty($rankmath_options)) {
            foreach ($rankmath_options as $key => $value) {
                if (strpos($key, 'noindex-post-type-') !== false && $value === 'on') {
                    $post_type = str_replace('noindex-post-type-', '', $key);
                    $disabled_post_types[] = $post_type;
                }
            }
        }
    }

    // All in One SEO
    if (class_exists('AIOSEO')) {
        $aioseo_options = get_option('aioseo_post_types');
        if (!empty($aioseo_options)) {
            foreach ($aioseo_options as $post_type => $settings) {
                if (!empty($settings['robotsMeta']) && in_array('noindex', $settings['robotsMeta'])) {
                    $disabled_post_types[] = $post_type;
                }
            }
        }
    }
    
    // Entferne deaktivierte Post-Typen
    return array_diff($all_post_types, $disabled_post_types);
}
