<?php
if (!defined('ABSPATH')) exit;

// Agregar menú al panel de administración
add_action('admin_menu', 'sirec_add_admin_menu');

function sirec_add_admin_menu() {
    add_menu_page(
        'Gestión de Solicitudes', // Título de la página
        'Solicitudes SIREC', // Título del menú
        'edit_iiiccab', // Capacidad requerida
        'sirec-applications', // Slug del menú
        'sirec_applications_page', // Función que muestra la página
        'dashicons-clipboard', // Icono
        30 // Posición
    );

    add_submenu_page(
        'sirec-applications',
        'Nueva Invitación',
        'Nueva Invitación',
        'edit_iiiccab',
        'sirec-new-invitation',
        'sirec_new_invitation_page'
    );
}

function sirec_applications_page() {
    if (!current_user_can('edit_iiiccab')) {
        wp_die(__('No tienes permiso para acceder a esta página.'));
    }
    
    include SIREC_PLUGIN_DIR . 'templates/applications-list.php';
}

function sirec_new_invitation_page() {
    if (!current_user_can('edit_iiiccab')) {
        wp_die(__('No tienes permiso para acceder a esta página.'));
    }
    
    include SIREC_PLUGIN_DIR . 'templates/new-invitation.php';
}