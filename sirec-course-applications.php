<?php
/*
Plugin Name: SIREC Course Applications
Description: Sistema de gestión de solicitudes de cursos para SIREC
Version: 1.0
Author: Tu Nombre
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
}

// Cargar archivos del plugin
require_once SIREC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once SIREC_PLUGIN_DIR . 'includes/applications-list.php';
require_once SIREC_PLUGIN_DIR . 'includes/form-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/enrollment-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/notifications.php';

// Agregar scripts y estilos
add_action('admin_enqueue_scripts', 'sirec_admin_scripts');

function sirec_admin_scripts($hook) {
    if ('toplevel_page_sirec-applications' !== $hook) {
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

    wp_localize_script('sirec-admin-script', 'sirecAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sirec_nonce')
    ]);
}