<?php
/*
Plugin Name: SIREC Course Applications
Description: Sistema de gestión de solicitudes de cursos para SIREC
Version: 1.0
Author: Plugin SIREC 
*/

if (!defined('ABSPATH')) exit;

// Constantes del plugin
define('SIREC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIREC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Activación del plugin
register_activation_hook(__FILE__, 'sirec_activate_plugin');

function sirec_activate_plugin() {
    // Crear tabla de solicitudes
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


    // Agregar capacidades grant 
    $role = get_role('editor_iiiccab');
    if($role) {
        $role->add_cap('edit_iiiccab');
    } else {
        // Si el rol no existe, lo creamos
        add_role('editor_iiiccab', 'Editor IIICCAB', array(
            'read' => true,
            'edit_iiiccab' => true
        ));
    }
}

// Cargar archivos del plugin
require_once SIREC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once SIREC_PLUGIN_DIR . 'includes/applications-list.php';
require_once SIREC_PLUGIN_DIR . 'includes/form-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/enrollment-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/notifications.php';
require_once SIREC_PLUGIN_DIR . 'includes/invitation-handler.php';

// Agregar scripts y estilos
// Agregar al inicio del archivo sirec-course-applications.php, después de la definición del plugin
add_action('plugins_loaded', function() {
    if (!function_exists('buddypress')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>El plugin SIREC Course Applications requiere que BuddyBoss Platform esté instalado y activado.</p></div>';
        });
    }
}, 999);

add_action('admin_enqueue_scripts', 'sirec_admin_scripts');

function sirec_admin_scripts($hook) {
    // Modificar la condición para incluir también la página de nueva invitación
    if (!in_array($hook, ['toplevel_page_sirec-applications', 'solicitudes-sirec_page_sirec-new-invitation'])) {
        return;
    }

    // Estilos y scripts existentes
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

    // Agregar Select2
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

// Agregar después de las otras acciones
add_action('plugins_loaded', 'sirec_check_buddyboss');

function sirec_check_buddyboss() {
    if (function_exists('buddypress')) {
        add_action('bp_setup_components', 'sirec_register_buddyboss_component');
    }
}

function sirec_register_buddyboss_component() {
    if (function_exists('bp_notifications_register_component')) {
        bp_notifications_register_component(
            'sirec_courses',
            array(
                'format_callback' => 'sirec_format_buddyboss_notifications',
                'position' => 105
            )
        );
    }
}
function sirec_format_buddyboss_notifications($action, $item_id, $secondary_item_id, $total_items, $format = 'string') {
    switch ($action) {
        case 'new_course_invitation':
            $course_title = get_the_title($item_id);
            $course_link = get_permalink($item_id);
            
            if ('string' === $format) {
                return sprintf(
                    'Has sido invitado al curso: <a href="%s">%s</a>',
                    esc_url($course_link),
                    esc_html($course_title)
                );
            } else {
                return array(
                    'text' => sprintf('Has sido invitado al curso: %s', $course_title),
                    'link' => $course_link
                );
            }
            break;
    }
    
    return $action;
}