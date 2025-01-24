<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_nopriv_sirec_submit_application', 'sirec_unauthorized_submission');
add_action('wp_ajax_sirec_submit_application', 'sirec_handle_application_submission');

function sirec_unauthorized_submission() {
    wp_send_json_error('Debes iniciar sesión para enviar una solicitud.');
}
function sirec_handle_application_submission() {
    check_ajax_referer('sirec_application_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Debes iniciar sesión para enviar una solicitud.');
    }
    
    $current_user = wp_get_current_user();
    $token = sanitize_text_field($_POST['token']);
    
    global $wpdb;
    $token_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sirec_invitation_tokens 
        WHERE token = %s AND used = 0 AND expires_at > NOW() AND user_id = %d",
        $token,
        $current_user->ID
    ));
    
    if (!$token_data) {
        wp_send_json_error('Token inválido o no autorizado para este usuario');
    }


    if ($token_data->user_id !== $current_user->ID) {
        wp_send_json_error('No tienes permiso para usar este token de invitación');
    }
    
    // Insert application
    $result = $wpdb->insert(
        $wpdb->prefix . 'course_applications',
        array(
            'user_id' => $token_data->user_id,
            'course_id' => $token_data->course_id,
            'status' => 'pending',
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'birth_date' => sanitize_text_field($_POST['birth_date']),
            'birth_country' => sanitize_text_field($_POST['birth_country']),
            'residence_country' => sanitize_text_field($_POST['residence_country']),
            'participation_reason' => sanitize_textarea_field($_POST['participation_reason']),
            'profession' => sanitize_text_field($_POST['profession']),
            'submission_date' => current_time('mysql')
        )
    );
    
    if ($result) {
        // Mark token as used
        $wpdb->update(
            $wpdb->prefix . 'sirec_invitation_tokens',
            array('used' => 1),
            array('token' => $token)
        );
        
        wp_send_json_success('Solicitud enviada correctamente');
    } else {
        wp_send_json_error('Error al guardar la solicitud');
    }
}