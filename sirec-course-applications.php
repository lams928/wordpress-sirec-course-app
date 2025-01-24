<?php
/*
Plugin Name: SIREC Course Applications
Description: Sistema de gestión de solicitudes de cursos para SIREC
Version: 1.0
Author: Plugin SIREC 
*/

if (!defined('ABSPATH')) exit;

define('SIREC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIREC_PLUGIN_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'sirec_activate_plugin');

function sirec_activate_plugin() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'course_applications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        course_id bigint(20) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        submission_date datetime DEFAULT CURRENT_TIMESTAMP,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        birth_date date NOT NULL,
        birth_country varchar(100) NOT NULL,
        residence_country varchar(100) NOT NULL,
        participation_reason text NOT NULL,
        profession varchar(100) NOT NULL,
        editor_notes text,
        review_date datetime,
        reviewed_by bigint(20),
        moodle_enrollment_status varchar(20),
        learndash_enrollment_status varchar(20),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    $tokens_table = $wpdb->prefix . 'sirec_invitation_tokens';
    $sql = "CREATE TABLE IF NOT EXISTS $tokens_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        token varchar(32) NOT NULL,
        user_id bigint(20) NOT NULL,
        course_id bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime NOT NULL,
        used tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY token (token)
    ) $charset_collate;";
    
    dbDelta($sql);

    $role = get_role('editor_iiiccab');
    if($role) {
        $role->add_cap('edit_iiiccab');
    } else {
        add_role('editor_iiiccab', 'Editor IIICCAB', array(
            'read' => true,
            'edit_iiiccab' => true
        ));
    }
}

require_once SIREC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once SIREC_PLUGIN_DIR . 'includes/applications-list.php';
require_once SIREC_PLUGIN_DIR . 'includes/form-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/enrollment-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/notifications.php';
require_once SIREC_PLUGIN_DIR . 'includes/invitation-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/class-bp-sirec-notification.php';

add_action('plugins_loaded', function() {
    if (!sirec_check_dependencies()) {
        return;
    }
    
    require_once SIREC_PLUGIN_DIR . 'includes/admin-menu.php';
    require_once SIREC_PLUGIN_DIR . 'includes/applications-list.php';
    require_once SIREC_PLUGIN_DIR . 'includes/form-handler.php';
    require_once SIREC_PLUGIN_DIR . 'includes/enrollment-handler.php';
    require_once SIREC_PLUGIN_DIR . 'includes/notifications.php';
    require_once SIREC_PLUGIN_DIR . 'includes/invitation-handler.php';
    require_once SIREC_PLUGIN_DIR . 'includes/class-bp-sirec-notification.php';
    
    add_action('wp_ajax_sirec_get_courses', function() {
        check_ajax_referer('sirec_nonce', 'nonce');
        
        $courses = sirec_get_edwiser_courses();
        $formatted_courses = array_map(function($course) {
            return array(
                'id' => $course->ID,
                'title' => $course->post_title
            );
        }, $courses);
        
        wp_send_json_success($formatted_courses);
    });
    
}, 20);

function sirec_check_dependencies() {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    
    if (!is_plugin_active('edwiser-bridge/edwiser-bridge.php')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>El plugin SIREC Course Applications requiere que Edwiser Bridge esté instalado y activado.</p></div>';
        });
        return false;
    }
    return true;
}
function sirec_get_edwiser_courses() {
    $args = array(
        'post_type' => 'eb_course',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    return get_posts($args);
}


add_action('admin_enqueue_scripts', 'sirec_admin_scripts');



function sirec_admin_scripts($hook) {
    if (!in_array($hook, ['toplevel_page_sirec-applications', 'solicitudes-sirec_page_sirec-new-invitation'])) {
        return;
    }

    wp_enqueue_style('sirec-admin-style', 
        SIREC_PLUGIN_URL . 'assets/css/admin-style.css', 
        [], 
        '1.0.0'
    );

    wp_enqueue_script('sirec-admin-script', 
        SIREC_PLUGIN_URL . 'assets/js/admin-script.js', 
        ['jquery'], 
        '1.0.0', 
        true
    );

    wp_enqueue_style('select2', 
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', 
        [], 
        '4.1.0'
    );
    
    wp_enqueue_script('select2', 
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 
        ['jquery'], 
        '4.1.0', 
        true
    );

    wp_localize_script('sirec-admin-script', 'sirecAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sirec_nonce')
    ]);
}
add_action('plugins_loaded', 'sirec_check_buddyboss');

function sirec_check_buddyboss() {
    if (function_exists('buddypress')) {
        add_action('bp_setup_components', 'sirec_register_buddyboss_component');
    }
}

add_action('bp_setup_components', 'sirec_register_buddyboss_component', 10);
add_action('bp_init', function() {
    if (class_exists('BP_SIREC_Notification')) {
        BP_SIREC_Notification::instance();
    }
});


function sirec_register_buddyboss_component() {
    if (function_exists('bp_notifications_register_component')) {
        bp_notifications_register_component('sirec_courses');
    }
}

function sirec_format_buddyboss_notifications($action, $item_id, $secondary_item_id, $total_items, $format = 'string') {
    if ($action !== 'new_course_invitation') {
        return $action;
    }

    $course_title = get_the_title($item_id);
    $course_link = get_permalink($item_id);
    
    if ('string' === $format) {
        $return = sprintf(
            __('Has sido invitado al curso: <a href="%s">%s</a>', 'sirec'),
            esc_url($course_link),
            esc_html($course_title)
        );
    } else {
        $return = array(
            'text' => sprintf(__('Has sido invitado al curso: %s', 'sirec'), $course_title),
            'link' => $course_link
        );
    }

    return $return;
}

// In sirec-course-applications.php
add_action('init', 'sirec_add_rewrite_rules');

function sirec_add_rewrite_rules() {
    add_rewrite_rule(
        'solicitud-curso/?$',
        'index.php?pagename=solicitud-curso',
        'top'
    );
}

add_filter('template_include', 'sirec_load_application_template');

function sirec_load_application_template($template) {
    if (get_query_var('pagename') === 'solicitud-curso') {
        if (!isset($_GET['token'])) {
            wp_redirect(home_url());
            exit;
        }
        
        // Si el usuario no está logueado, redirigir al login
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
            exit;
        }
        
        return SIREC_PLUGIN_DIR . 'templates/application-form.php';
    }
    return $template;
}