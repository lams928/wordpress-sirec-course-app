<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_sirec_send_invitations', 'sirec_handle_send_invitations');

function sirec_handle_send_invitations() {
    check_ajax_referer('sirec_invitation_nonce', 'nonce');
    
    if (!current_user_can('edit_iiiccab')) {
        wp_send_json_error('No tienes permisos para enviar invitaciones.');
    }
    
    $course_id = intval($_POST['course_id']);
    $custom_message = sanitize_textarea_field($_POST['message']);
    $selected_users = array_map('intval', $_POST['selected_users']);
    
    if(empty($selected_users)) {
        wp_send_json_error('Debes seleccionar al menos un usuario.');
    }
    
    $sent_count = 0;
    $errors = [];
    
    foreach($selected_users as $user_id) {
        $user = get_user_by('id', $user_id);
        if(!$user) continue;
        
        // Enviar email
        $email_sent = sirec_send_invitation_email($user, $course_id, $custom_message);
        
        // Enviar notificación SIREC
        $notification_sent = sirec_send_sirec_notification($user_id, $course_id);
        
        if($email_sent && $notification_sent) {
            $sent_count++;
        } else {
            $errors[] = $user->user_email;
        }
    }
    
    $response = [
        'message' => sprintf(
            'Invitaciones enviadas exitosamente a %d usuarios.%s',
            $sent_count,
            !empty($errors) ? ' Errores: ' . implode(', ', $errors) : ''
        )
    ];
    
    wp_send_json_success($response);
}

function sirec_send_invitation_email($user, $course_id, $custom_message) {
    $course = get_post($course_id);
    $subject = sprintf('Invitación al curso: %s', $course->post_title);
    
    $message = sprintf(
        'Hola %s,<br><br>'.
        'Has sido invitado/a a participar en el curso "%s".<br><br>',
        $user->display_name,
        $course->post_title
    );
    
    if(!empty($custom_message)) {
        $message .= $custom_message . '<br><br>';
    }
    
    $message .= sprintf(
        'Para inscribirte, por favor completa el formulario de solicitud:<br>'.
        '<a href="%s">Completar formulario</a><br><br>'.
        'Saludos cordiales,<br>'.
        'Equipo SIREC',
        home_url('/solicitud-curso/?course_id=' . $course_id)
    );
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

function sirec_send_sirec_notification($user_id, $course_id) {
    global $wpdb;
    
    // Implementar aquí la lógica para enviar notificación en el sistema SIREC
    // Este es un ejemplo básico, ajústalo según tu sistema de notificaciones
    $table_name = $wpdb->prefix . 'sirec_notifications';
    
    return $wpdb->insert(
        $table_name,
        [
            'user_id' => $user_id,
            'type' => 'course_invitation',
            'reference_id' => $course_id,
            'message' => sprintf(
                'Has sido invitado al curso: %s',
                get_the_title($course_id)
            ),
            'created_at' => current_time('mysql')
        ],
        ['%d', '%s', '%d', '%s', '%s']
    );
}