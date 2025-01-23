<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_sirec_handle_application', 'sirec_handle_application');

function sirec_handle_application() {
    check_ajax_referer('sirec_nonce', 'nonce');
    
    if (!current_user_can('edit_iiiccab')) {
        wp_send_json_error('No tienes permisos para realizar esta acción.');
    }
    
    $application_id = intval($_POST['application_id']);
    $decision = sanitize_text_field($_POST['decision']);
    $notes = sanitize_textarea_field($_POST['notes']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'course_applications';
    
    $result = $wpdb->update(
        $table_name,
        [
            'status' => $decision,
            'editor_notes' => $notes,
            'review_date' => current_time('mysql'),
            'reviewed_by' => get_current_user_id()
        ],
        ['id' => $application_id],
        ['%s', '%s', '%s', '%d'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Error al actualizar la solicitud.');
    }
    
    if ($decision === 'approved') {
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $application_id
        ));
        
        $enrollment = new SIREC_Enrollment_Handler();
        $enrollment->process_enrollment($application);
    }
    
    SIREC_Notifications::send_application_notification($application_id, $decision);
    
    wp_send_json_success('Solicitud procesada correctamente.');
}