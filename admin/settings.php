<?php
if (!defined('ABSPATH')) {
    exit;
}

global $current_post_type;

function acur_settingsUpdated()
{
    if(
        isset($_GET['page']) && $_GET['page']=="acur-settings" 
        && isset($_GET['settings-updated']) && $_GET['settings-updated']=="true" 
    )
    {
        do_action('acur_after_options_saved');
    }
}


// Admin-Menü hinzufügen
function acur_admin_menu() {
    add_menu_page(
        __('Advanced Content Update Refresher', 'advanced-content-update-refresher'), // Titel der Seite
        __('Update Refresher', 'advanced-content-update-refresher'), // Menüname
        'manage_options', // Benutzerrechte
        'acur-settings', // Slug
        'acur_settings_page', // Funktion, die die Seite anzeigt
        'dashicons-update', // Icon für das Menü (Hier: WordPress-Update-Icon)
        80 // Position im Admin-Menü
    );
}
add_action('admin_menu', 'acur_admin_menu');

// Einstellungen registrieren
function acur_register_settings() {
    $post_types = acur_get_all_post_types();
    
    foreach ($post_types as $post_type) {
        register_setting("acur_settings_group_{$post_type}", "acur_settings_{$post_type}");
    }
}

function acur_save_settings() 
{
    global $optionsFormSent;
    $post_types = acur_get_all_post_types();
    
    $acur_settings = [];

    foreach ($post_types as $post_type) {
        $settings = get_option("acur_settings_{$post_type}");

        if (!empty($settings)) {
            $acur_settings[$post_type] = $settings;
        }
    }
    
    update_option('acur_settings', $acur_settings);
}

add_action('admin_init', 'acur_set_global_posttype');
add_action('admin_init', 'acur_settingsUpdated');
add_action('admin_init', 'acur_save_settings');
add_action('admin_init', 'acur_register_settings');

// Funktion zum Abrufen der Sitemap-URLs
function acur_get_sitemap_url() {
    if (class_exists('WPSEO_Sitemaps')) {
        return home_url('/sitemap_index.xml'); // Yoast SEO
    } elseif (class_exists('RankMath')) {
        return home_url('/sitemap_index.xml'); // RankMath SEO
    } elseif (class_exists('AIOSEO')) {
        return home_url('/sitemap.xml'); // All in One SEO
    } else {
        return home_url('/sitemap.xml'); // Standard Google XML Sitemap
    }
}

// Funktion zur Überprüfung, ob ein CPT in SEO-Plugins deaktiviert ist
function acur_is_post_type_active($post_type) {
    // Prüfe, ob Yoast SEO installiert ist und ob der CPT deaktiviert wurde
    $yoast_options = get_option('wpseo_titles');
    if ($yoast_options && isset($yoast_options["post_types-{$post_type}-not_in_sitemap"]) && $yoast_options["post_types-{$post_type}-not_in_sitemap"]) {
        return false;
    }

    // Prüfe, ob Rank Math installiert ist und ob der CPT deaktiviert wurde
    $rank_math_options = get_option('rank-math-options-titles');
    if ($rank_math_options && isset($rank_math_options["pt_{$post_type}_noindex"]) && $rank_math_options["pt_{$post_type}_noindex"]) {
        return false;
    }

    return true;
}

// Änderung der Post Type Abfrage, um SEO-Filter zu berücksichtigen
// Verwende die bestehende Funktion aus post-types.php
require_once ACUR_PLUGIN_DIR . 'includes/post-types.php';

add_action('wp_ajax_acur_get_total_posts', 'acur_get_total_posts');

function acur_get_total_posts() 
{
    $total = 0;
    
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    if (!$post_type) {
        wp_send_json_error(__('Post type is missing!', 'advanced-content-update-refresher'));
    }
    
    $total = wp_count_posts($post_type)->publish;

    wp_send_json_success(array('total' => $total));
}

// AJAX-Handler für die Massenaktualisierung
add_action('wp_ajax_acur_mass_update', 'acur_mass_update');

function acur_mass_update() 
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('No permission!', 'advanced-content-update-refresher'));
    }

    if (!check_ajax_referer('acur_mass_update', 'security', false)) {
        wp_send_json_error(__('Invalid security check!', 'advanced-content-update-refresher'));
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    if (!$post_type) {
        wp_send_json_error(__('Post type is missing!', 'advanced-content-update-refresher'));
    }
    
    acur_do_update($post_type,(int)$_POST['offset'],(int)$_POST['limit'],1);
}

function acur_set_global_posttype()
{
    global $current_post_type;

    if (!isset($current_post_type) || empty($current_post_type))
    {
        $allowed_post_types = acur_get_all_post_types();
        
        if (isset($_POST['post_type']) && in_array($_POST['post_type'],$allowed_post_types))
        {
            $current_post_type = sanitize_text_field($_POST['post_type']);
        }
        elseif(isset($_GET['tab']) && in_array($_GET['tab'],$allowed_post_types))
        {
            $current_post_type = sanitize_text_field($_GET['tab']);
        }
        else
        {
            $current_post_type = 'post';
        }
    }
}

// Admin-Seite rendern
function acur_settings_page() {
    $post_types = acur_get_all_post_types();
    $sitemap_url = acur_get_sitemap_url();
    global $current_post_type;

    if (empty($post_types)) {
        echo '<p>' . __('No valid post types found.', 'advanced-content-update-refresher') . '</p>';
        return;
    }

    ?>

    <div class="wrap">
        <h1><?php _e('Advanced Content Update Refresher', 'advanced-content-update-refresher'); ?></h1>

        <h2 class="nav-tab-wrapper">
            <?php foreach ($post_types as $post_type): ?>
                <a href="?page=acur-settings&tab=<?php echo esc_attr($post_type); ?>"
                   class="nav-tab <?php echo ($current_post_type == $post_type) ? 'nav-tab-active' : ''; ?>">
                    <?php echo ucfirst($post_type); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <?php foreach ($post_types as $post_type): 
            if ($current_post_type !== $post_type) continue; 
            $settings = get_option("acur_settings_{$post_type}", []);
        ?>
            <div class="tab-content">
                <h3><?php echo ucfirst($post_type); ?> <?php _e('Settings', 'advanced-content-update-refresher'); ?></h3>

                <form method="post" action="options.php">
                    <?php settings_fields("acur_settings_group_{$post_type}"); ?>
                    <?php do_settings_sections("acur-settings-{$post_type}"); ?>
                    <input type="hidden" name="post_type" value="<?php echo $current_post_type;?>" />
                    <input type="hidden" name="acur_settings_<?php echo esc_attr($post_type); ?>[mass_update_field]" value="<?php echo $settings["mass_update_field"];?>" />
                    <input type="hidden" name="mm_settings_saved" value="1" />
                    <p><strong><?php _e('Update Interval', 'advanced-content-update-refresher'); ?></strong></p>
                    <p><select name="acur_settings_<?php echo esc_attr($post_type); ?>[frequency]" id="acur_frequency_<?php echo esc_attr($post_type); ?>" onchange="toggleCustomDays('<?php echo esc_attr($post_type); ?>')">
                        <option value="hourly" <?php selected($settings["frequency"] ?? "", "hourly"); ?>><?php _e('Hourly', 'advanced-content-update-refresher'); ?></option>
                        <option value="daily" <?php selected($settings["frequency"] ?? "", "daily"); ?>><?php _e('Daily', 'advanced-content-update-refresher'); ?></option>
                        <option value="weekly" <?php selected($settings["frequency"] ?? "", "weekly"); ?>><?php _e('Weekly', 'advanced-content-update-refresher'); ?></option>
                        <option value="biweekly" <?php selected($settings["frequency"] ?? "", "biweekly"); ?>><?php _e('Every 2 Weeks', 'advanced-content-update-refresher'); ?></option>
                        <option value="monthly" <?php selected($settings["frequency"] ?? "", "monthly"); ?>><?php _e('Monthly', 'advanced-content-update-refresher'); ?></option>
                        <option value="custom" <?php selected($settings["frequency"] ?? "", "custom"); ?>><?php _e('Custom (Days)', 'advanced-content-update-refresher'); ?></option>
                    </select></p>
                    
                    <p>
                        <input type="number" name="acur_settings_<?php echo esc_attr($post_type); ?>[custom_days]" id="acur_custom_days_<?php echo esc_attr($post_type); ?>" value="<?php echo esc_attr($settings['custom_days'] ?? 7); ?>" min="1" style="display: <?php echo ($settings['frequency'] ?? '') === 'custom' ? 'inline-block' : 'none'; ?>;"></p>
                    <p>
                        <input type="checkbox" name="acur_settings_<?php echo esc_attr($post_type); ?>[random_variance]" value="1" <?php checked($settings['random_variance'] ?? '', '1'); ?>>
                        <?php _e('Enable 10% Random Variance', 'advanced-content-update-refresher'); ?>
                    </p>

                    <p><strong><?php _e('Posts per Update', 'advanced-content-update-refresher'); ?></strong></p>
                    <p><input type="number" name="acur_settings_<?php echo esc_attr($post_type); ?>[posts_per_run]" value="<?php echo esc_attr($settings['posts_per_run'] ?? 5); ?>" min="1"></p>

                    <p><strong><?php _e('Field to Update', 'advanced-content-update-refresher'); ?></strong></p>
                    <p><select name="acur_settings_<?php echo esc_attr($post_type); ?>[update_field]">
                        <option value="modified" <?php selected($settings["update_field"] ?? "", "modified"); ?>><?php _e('Modified', 'advanced-content-update-refresher'); ?></option>
                        <option value="published" <?php selected($settings["update_field"] ?? "", "published"); ?>><?php _e('Published', 'advanced-content-update-refresher'); ?></option>
                    </select></p>

                    <p>
                        <input 
                                id="acur-save-settings" 
                                type="submit" 
                                class="button button-primary" 
                                data-post-type="<?php echo esc_attr($current_post_type); ?>" 
                                value="<?php _e('Save Changes', 'advanced-content-update-refresher'); ?>">
                        </p>
                </form>
                
                <hr />
                
                <h3><?php _e('Bulk Update', 'advanced-content-update-refresher'); ?></h3>
                
                 <form method="post" action="options.php">
                    <?php settings_fields("acur_settings_group_{$post_type}"); ?>
                    <?php do_settings_sections("acur-settings-{$post_type}"); ?>
                    <input type="hidden" name="post_type" value="<?php echo $current_post_type;?>" />
                    
                    <input type="hidden" name="acur_settings_<?php echo esc_attr($post_type); ?>[frequency]" value="<?php echo $settings["frequency"];?>" />
                    <input type="hidden" name="acur_settings_<?php echo esc_attr($post_type); ?>[custom_days]" value="<?php echo $settings["custom_days"];?>" />
                    <input type="hidden" name="acur_settings_<?php echo esc_attr($post_type); ?>[random_variance]" value="<?php echo (int)$settings['random_variance'];?>" />
                    <input type="hidden" name="acur_settings_<?php echo esc_attr($post_type); ?>[posts_per_run]" value="<?php echo $settings["posts_per_run"];?>" />
                    <input type="hidden" name="acur_settings_<?php echo esc_attr($post_type); ?>[update_field]" value="<?php echo $settings["update_field"];?>" />
                    
                    <input type="hidden" name="mm_settings_saved" value="1" />
                    
                    <p><strong><?php _e('Field to Update', 'advanced-content-update-refresher'); ?></strong></p>
                    <p><select name="acur_settings_<?php echo esc_attr($post_type); ?>[mass_update_field]">
                        <option value="modified" <?php selected($settings["mass_update_field"] ?? "", "modified"); ?>><?php _e('Modified', 'advanced-content-update-refresher'); ?></option>
                        <option value="published" <?php selected($settings["mass_update_field"] ?? "", "published"); ?>><?php _e('Published', 'advanced-content-update-refresher'); ?></option>
                    </select></p>

                    <p>
                        <input 
                                id="acur-save-settings" 
                                type="submit" 
                                class="button button-primary" 
                                data-post-type="<?php echo esc_attr($current_post_type); ?>" 
                                value="<?php _e('Save Changes', 'advanced-content-update-refresher'); ?>">
                        </p>
               </form> 
                
                <?php if(!is_array($settings) || !isset($settings["mass_update_field"]))
                {
                    echo "<p>";
                    _e('To perform the bulk update, please first save the settings for the post type.', 'advanced-content-update-refresher');
                    echo "</p>";
                }
                else
                {
                ?>
                    <p><button class="button button-secondary acur-mass-update" data-post-type="<?php echo esc_attr($post_type); ?>">
                        <?php _e('Update All', 'advanced-content-update-refresher'); ?>
                    </button></p>
                <?php
                }
                ?>
                <div class="acur_progress_container <?php echo esc_attr($current_post_type); ?>"><div class="acur_progress_bar"></div><div class="acur_progress_text"></div></div>
                
                <hr />
            </div>
        <?php endforeach; ?>

        <p>
            <strong><?php _e('Link to Sitemap', 'advanced-content-update-refresher'); ?>:</strong>
            <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank"><?php _e('View Sitemap', 'advanced-content-update-refresher'); ?></a>
        </p>
    </div>

    <script>
        function toggleCustomDays(postType) {
            var frequency = document.getElementById('acur_frequency_' + postType).value;
            var customDaysField = document.getElementById('acur_custom_days_' + postType);
            if (frequency === 'custom') {
                customDaysField.style.display = 'inline-block';
            } else {
                customDaysField.style.display = 'none';
            }
        }
        
        jQuery(document).ready(function($) 
        {
            $('.acur-mass-update').click(function() 
            {
                var updateBtn = $(this);
                var postType = updateBtn.data('post-type');
                var progressBar = $('.acur_progress_bar');
                var progressText = $('.acur_progress_text');
                var progressContainer = $('.acur_progress_container.'+postType);
                var postType = updateBtn.data('post-type');
                var batchSize =50; // Anzahl pro Durchgang
                var offset = 0;
                var totalPosts = 0;
                var updatedPosts = 0;

                updateBtn.prop('disabled', true);
                progressContainer.show();

                function updateProgress() {
                    var percentage = (updatedPosts / totalPosts) * 100;
                    progressBar.css('width', percentage + '%');
                    percentage=Math.round(percentage);
                    progressText.text(percentage + '% <?php _e('updated', 'advanced-content-update-refresher'); ?>');
                }

                function fetchTotalPosts() 
                {
                    $.post(ajaxurl, { action: 'acur_get_total_posts', post_type: postType, security: '<?php echo wp_create_nonce("acur_get_total_posts"); ?>' }, function(response) {
                        totalPosts = response.data.total;
                        var percentage=updatedPosts*100/totalPosts;
                        percentage=Math.round(percentage);
                        progressText.text(percentage + '% <?php _e('updated', 'advanced-content-update-refresher'); ?>');
                        processBatch();
                    });
                }

                function processBatch() 
                {                
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'acur_mass_update',
                            offset: offset, 
                            limit: batchSize,
                            post_type: postType,
                            security: '<?php echo wp_create_nonce("acur_mass_update"); ?>'
                        },
                        success: function(response) 
                        {
                            updatedPosts += response.data.updated;
                            offset += batchSize;
                            updateProgress();

                            if (updatedPosts < totalPosts) 
                            {
                                processBatch();
                            }
                            
                            else 
                            {
                                updateBtn.prop('disabled', false);
                                alert('<?php _e('Bulk update successfully completed', 'advanced-content-update-refresher'); ?>');
                            }
                        },
                        
                        error: function() {
                            alert('AJAX request failed!');
                            button.prop('disabled', false).text('Update All');
                        }
                    });
                }

                fetchTotalPosts();
            });
        });
    </script>

    <style>
    /* CSS-Anpassung für das Eingabefeld */
    select,
    input[type="number"] {
        vertical-align: middle; /* Vertikale Ausrichtung */
        display: inline-block; /* Inline-Block für die gleiche Höhe */
        width: auto; /* Automatische Breite */
    }
    
    .acur_progress_container
    {
        border: 1px solid rgba(0,0,0,.15);
        border-radius: 3px;
        background-color: white;
        height: 25px;
        width: 300px;
        margin-top: 15px;
        text-align: center;
        display:none;
    }

    .acur_progress_bar {
        background-color: #c4ff00;
        padding-top:4px;
        padding-bottom:5px;
        height:calc(100% - 9px);
        width:0%;
    }
    
    .acur_progress_text {
        color: #3c434a;
        font-weight: bold;
        position: relative;
        top: -22px;
    }
    
    </style>

    <?php
}

?>
