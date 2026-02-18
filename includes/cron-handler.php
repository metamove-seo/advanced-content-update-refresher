<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sofortige Ausführung nach „Änderungen speichern“
 */
function acur_instant_update() {

    global $current_post_type;

    if (isset($current_post_type)) {
        acur_update_post_type($current_post_type); // Führt die Aktualisierung sofort aus
    }
    
}

// Funktionen nach „Einstellungen speichern“ ausgeführen
add_action('acur_after_options_saved', 'acur_instant_update', 100);
add_action('acur_after_options_saved', 'acur_clear_crons', 200);
add_action('acur_after_options_saved', 'acur_schedule_crons', 300);

// (OPTIONAL) Erzwingt die sofortige Ausführung von geplanten Cronjobs
add_action('acur_force_cron_execution', function() {
    spawn_cron();
});

/**
 * Cronjobs entfernen (bei Plugin-Deaktivierung)
 */
function acur_clear_crons() {
    
    $post_types = acur_get_all_post_types();
    
    foreach ($post_types as $post_type) {
        $timestamp = wp_next_scheduled("acur_update_{$post_type}", array('post_type' => $post_type));
        
        if ($timestamp) {
            wp_unschedule_event($timestamp, "acur_update_{$post_type}", array('post_type' => $post_type));
        }
    }
}

/**
 * Beiträge für einen Post-Typ aktualisieren
 */
 
function acur_update_post_type($post_type)
{
    if (is_array($post_type)) {$post_type=$post_type['post_type'];}

    if(!empty($post_type))
    {
        acur_do_update($post_type);
    }
}

function acur_add_schedules( $schedules ) 
{
    $schedules['biweekly'] = array('interval' => 1209600, 'display' => esc_html__( 'Every 2 Weeks', 'advanced-content-update-refresher'),);
    
    $post_types = acur_get_all_post_types();
    
    foreach ($post_types as $mm_post_type) 
    {
        $settings = get_option("acur_settings_{$mm_post_type}", []);

        if(count($settings) > 0 && $settings["frequency"]=="custom")
        {
            $tage=(int)$settings["custom_days"];
            $intervall=60*60*24*$tage;
            $schedules["acur_custom_{$mm_post_type}"] = array('interval' => $intervall, 'display' => esc_html__('Custom', 'advanced-content-update-refresher'). ' ('.strtoupper($mm_post_type).') '.esc_html__('Every', 'advanced-content-update-refresher').$tage.esc_html__('days', 'advanced-content-update-refresher'));
        }
    }
    
    return $schedules;
}


/**
 * Planmäßige Cronjobs registrieren
 */
function acur_schedule_crons() 
{
    $post_types = acur_get_all_post_types();

    foreach ($post_types as $mm_post_type) 
    {
        if (!wp_next_scheduled("acur_update_{$mm_post_type}", array('post_type' => $mm_post_type))) 
        {
            $settings = get_option("acur_settings_{$mm_post_type}", []);

            if(count($settings) > 0)
            {
                if($settings["frequency"]=="custom")
                {
                    $settings["frequency"]="acur_custom_{$mm_post_type}";
                }

                wp_schedule_event(time(), $settings["frequency"], "acur_update_{$mm_post_type}", array('post_type' => $mm_post_type));
            }
            
            unset($settings);
        }
        add_action("acur_update_{$mm_post_type}", 'acur_update_post_type');
    }
}


function acur_get_update_time($variance_status)
{
    $update_time = current_time('mysql');
    
    if ((int)$variance_status==1)
    {
        $newTime=strtotime($update_time);
        $hour=60*60;
        $prozent=random_int(1,10);
        $abweichung=round($hour*(100-$prozent)/100);
        $abweichung=$hour-$abweichung;
        $newTime=$newTime-$abweichung;
        $update_time = date("Y-m-d H:i:s",$newTime);
    }
    
    return $update_time;
}

function acur_do_update($post_type, $offset=0, $limit=0, $ajax=0)
{
    $update_settings = get_option('acur_settings');
    
    if (!isset($update_settings[$post_type])) 
    {
        if ($ajax==1)
        {
            wp_send_json_error(__('ERROR: No settings for the post type', 'advanced-content-update-refresher').$post_type.__('gefunden!', 'advanced-content-update-refresher'));
            return 'error';
        }
        else
        {
            return;
        }
        
        die();
    }
        
    if ($ajax==1)
    {
        if (!isset($update_settings[$post_type]['mass_update_field']) || empty($update_settings[$post_type]['mass_update_field'])) 
        {
            $sort_by="modified";
        }
        else
        {
            $sort_by=$update_settings[$post_type]['mass_update_field'];
        }
    }
    else
    {
        if (!isset($update_settings[$post_type]['update_field']) || empty($update_settings[$post_type]['update_field'])) 
        {
            $sort_by="modified";
        }
        else
        {
            $sort_by=$update_settings[$post_type]['update_field'];
        }
    }
    
    $posts_per_update = isset($update_settings[$post_type]['posts_per_run']) ? intval($update_settings[$post_type]['posts_per_run']) : 5;
    
    // für Massenaktualisierung
    if ($ajax==1) 
    {
        $posts_per_update=50;
        $sort_by="ID";
        $update_settings[$post_type]['random_variance']=0;
        $update_settings[$post_type]['update_field']=$update_settings[$post_type]['mass_update_field'];
    }
    
    $args = [
        'post_type'      => $post_type,
        'posts_per_page' => $posts_per_update,
        'post_status'    => 'publish',
        'orderby'        => $sort_by,
        'order'          => 'ASC',
    ];
    
    if((int)$offset>0) {$args['offset']=(int)$offset;}
    
    $posts = get_posts($args);
    $updated_count = 0;
    
    if(count($posts) > 0)
    {
        global $wpdb;
    
        foreach ($posts as $post) 
        {
            $update_time=acur_get_update_time($update_settings[$post_type]['random_variance']);
            
            $updateData=array('ID' => $post->ID,);
            
            switch($update_settings[$post_type]['update_field'])
            {
                case "modified": 
                    $wpdb->query("UPDATE ".$wpdb->prefix."posts SET post_modified='".$update_time."', post_modified_gmt='".get_gmt_from_date($update_time)."' WHERE ID=".(int)$post->ID);
                break;
                
                case "published":
                    $wpdb->query("UPDATE ".$wpdb->prefix."posts SET post_date='".$update_time."' WHERE ID=".(int)$post->ID);
                break;
            }
            
            $updated_count++;

            if (function_exists('wpseo_flush_sitemap_cache')) {
                wpseo_flush_sitemap_cache();
            }

            if (function_exists('aioseo')) {
                aioseo()->core->cache->clear();
            }
        }
        
        if ($ajax==1)
        {
            $rv=array("updated"=>$updated_count, "message" => __('Bulk update successfully completed', 'advanced-content-update-refresher'));
            wp_send_json_success($rv);
            return;
        }
        else
        {
            return $updated_count;
        }
    }
    else
    {
        if ($ajax==1)
        {
            wp_send_json_error(__('No Posts found!', 'advanced-content-update-refresher'));
            return;
        }
        else
        {
            return;
        }
    }
}
