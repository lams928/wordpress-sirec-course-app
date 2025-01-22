<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_sirec_send_invitations', 'sirec_handle_send_invitations');

function sirec_handle_send_invitations() {
    check_ajax_referer('sirec_invitation_nonce', 'nonce');
    
    error_log('Iniciando proceso de envío de invitaciones');
    
    if (!current_user_can('edit_iiiccab')) {
        error_log('Error: Usuario sin permisos necesarios');
        wp_send_json_error('No tienes permisos para enviar invitaciones.');
    }
    
    $course_id = intval($_POST['course_id']);
    $custom_message = sanitize_textarea_field($_POST['message']);
    $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];
    
    if(empty($selected_users)) {
        error_log('Error: No se seleccionaron usuarios');
        wp_send_json_error('Debes seleccionar al menos un usuario.');
    }
    
    $sent_count = 0;
    $errors = [];
    
    foreach($selected_users as $user_id) {
        $user = get_user_by('id', $user_id);
        if(!$user) {
            error_log("Error: Usuario no encontrado - ID: $user_id");
            $errors[] = "Usuario ID $user_id no encontrado";
            continue;
        }
        
        // Primero intentamos enviar la notificación
        $notification_sent = sirec_send_sirec_notification($user_id, $course_id);
        if(!$notification_sent) {
            error_log("Error al enviar notificación a: " . $user->user_email);
            $errors[] = "Error enviando notificación a " . $user->user_email;
            continue;
        }
        
        // Luego intentamos enviar el email
        // $email_sent = sirec_send_invitation_email($user, $course_id, $custom_message);
        // if(!$email_sent) {
        //     error_log("Error al enviar email a: " . $user->user_email);
        //     $errors[] = "Error enviando email a " . $user->user_email;
        //     continue;
        // }
        
        $sent_count++;
    }
    
    $response = [
        'success' => true,
        'sent_count' => $sent_count,
        'errors' => $errors,
        'message' => sprintf(
            'Invitaciones enviadas exitosamente a %d usuarios. %s',
            $sent_count,
            !empty($errors) ? 'Errores: ' . implode(', ', $errors) : ''
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
    if (!function_exists('bp_is_active') || !bp_is_active('notifications')) {
        error_log('BuddyBoss notifications no está disponible o activo');
        return false;
    }

    // Agregar notificación
    $notification_id = bp_notifications_add_notification(array(
        'user_id'           => $user_id,
        'item_id'           => $course_id,
        'secondary_item_id' => get_current_user_id(),
        'component_name'    => 'sirec_courses',
        'component_action'  => 'new_course_invitation',
        'date_notified'     => bp_core_current_time(),
        'is_new'           => 1,
    ));

    if (!$notification_id) {
        error_log('Error al crear la notificación en BuddyBoss para el usuario ' . $user_id);
        return false;
    }

    // Agregar actividad si está disponible
    if (bp_is_active('activity')) {
        $course_title = get_the_title($course_id);
        $course_link = get_permalink($course_id);
        
        bp_activity_add(array(
            'user_id'      => $user_id,
            'component'    => 'sirec_courses',
            'type'         => 'new_course_invitation',
            'primary_link' => $course_link,
            'item_id'      => $course_id,
            'action'       => sprintf(
                __('Has sido invitado al curso: <a href="%s">%s</a>', 'sirec'),
                esc_url($course_link),
                esc_html($course_title)
            ),
            'hide_sitewide' => false
        ));
    }

    // Limpiar caché
    wp_cache_delete($user_id, 'bp_notifications_unread_count');
    
    do_action('sirec_after_course_invitation_notification', $user_id, $course_id, $notification_id);

    return true;
}