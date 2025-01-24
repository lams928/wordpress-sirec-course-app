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

    // Preparar datos para inserción
    $application_data = array(
        'user_id' => $current_user->ID,
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
    );
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'course_applications',
        $application_data
    );
    
    if ($result) {
        // Marcar token como usado
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

add_action('wp_ajax_sirec_approve_application', 'sirec_handle_approve_application');
add_action('wp_ajax_sirec_reject_application', 'sirec_handle_reject_application');

function sirec_handle_approve_application() {
    check_ajax_referer('sirec_application_action', 'nonce');
    
    if (!current_user_can('edit_iiiccab')) {
        wp_send_json_error('No tienes permisos para realizar esta acción.');
    }
    
    $application_id = intval($_POST['application_id']);
    
    global $wpdb;
    $application = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}course_applications WHERE id = %d",
        $application_id
    ));
    
    if (!$application) {
        wp_send_json_error('Solicitud no encontrada.');
    }

    // Verificar si el usuario ya está matriculado
    $moodle_user_id = get_user_meta($application->user_id, 'moodle_user_id', true);
    $moodle_course_id = get_post_meta($application->course_id, 'moodle_course_id', true);

    if (!$moodle_user_id) {
        wp_send_json_error('Error: No se encontró el ID de Moodle para el usuario. Por favor, verifique que el usuario esté sincronizado con Moodle.');
    }

    if (!$moodle_course_id) {
        wp_send_json_error('Error: No se encontró el ID de Moodle para el curso. Por favor, verifique que el curso esté sincronizado con Moodle.');
    }

    // Verificar la conexión con Moodle
    // if (!class_exists('Eb_Connection_Helper')) {
    //     wp_send_json_error('Error: El plugin Edwiser Bridge no está instalado o activado correctamente.');
    // }

    try {
        // Iniciar proceso de matrícula
        $enrollment_handler = new SIREC_Enrollment_Handler();
        $enrollment_result = $enrollment_handler->process_enrollment($application);
        
        if ($enrollment_result['moodle']) {
            // Actualizar estado de la solicitud
            $update_result = $wpdb->update(
                $wpdb->prefix . 'course_applications',
                array(
                    'status' => 'approved',
                    'review_date' => current_time('mysql'),
                    'reviewed_by' => get_current_user_id(),
                    'moodle_enrollment_status' => 'success'
                ),
                array('id' => $application_id)
            );
            
            if ($update_result === false) {
                wp_send_json_error('Error al actualizar el estado de la solicitud en la base de datos.');
            }
            
            // Enviar notificación
            SIREC_Notifications::send_application_notification($application_id, 'approved');
            
            wp_send_json_success(array(
                'message' => 'Solicitud aprobada y usuario matriculado correctamente.',
                'user_id' => $moodle_user_id,
                'course_id' => $moodle_course_id
            ));
        } else {
            // Guardar el error en la base de datos
            $wpdb->update(
                $wpdb->prefix . 'course_applications',
                array(
                    'moodle_enrollment_status' => 'error',
                    'editor_notes' => 'Error en la matriculación: ' . date('Y-m-d H:i:s')
                ),
                array('id' => $application_id)
            );

            $error_message = sprintf(
                'Error al matricular al usuario en Moodle. Detalles: Usuario Moodle ID: %s, Curso Moodle ID: %s. ' .
                'Por favor, verifique: 1) La conexión con Moodle, 2) Los permisos del usuario, ' .
                '3) La disponibilidad del curso en Moodle.',
                $moodle_user_id,
                $moodle_course_id
            );
            
            wp_send_json_error($error_message);
        }
    } catch (Exception $e) {
        wp_send_json_error('Error inesperado: ' . $e->getMessage());
    }
}

function sirec_handle_reject_application() {
    check_ajax_referer('sirec_application_action', 'nonce');
    
    if (!current_user_can('edit_iiiccab')) {
        wp_send_json_error('No tienes permisos para realizar esta acción.');
    }
    
    $application_id = intval($_POST['application_id']);
    
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'course_applications',
        array(
            'status' => 'rejected',
            'review_date' => current_time('mysql'),
            'reviewed_by' => get_current_user_id()
        ),
        array('id' => $application_id)
    );
    
    if ($result) {
        SIREC_Notifications::send_application_notification($application_id, 'rejected');
        wp_send_json_success('Solicitud rechazada correctamente.');
    } else {
        wp_send_json_error('Error al rechazar la solicitud.');
    }
}