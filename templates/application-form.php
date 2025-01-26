<?php
// En tu template application-form.php

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_die('Debes iniciar sesión para acceder a este formulario. <a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">Iniciar sesión</a>');
}

$current_user = wp_get_current_user();

// Verify token
if (!isset($_GET['token'])) {
    wp_die('Token no proporcionado. Acceso denegado.');
}

$token = sanitize_text_field($_GET['token']);
global $wpdb;
$token_data = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sirec_invitation_tokens 
    WHERE token = %s AND used = 0 AND expires_at > NOW()",
    $token
));

if (!$token_data || $token_data->user_id != $current_user->ID) {
    wp_die('No tienes permiso para acceder a este formulario o el enlace ha expirado.');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $_POST['token'] = $token; // Añadir el token a los datos del POST
    $result = sirec_process_application_form($_POST);
    
    if ($result['success']) {
        // Obtener usuarios con el rol editor_iiiccab
        $editor_users = get_users(['role' => 'editor_iiiccab']);
        
        // Preparar lista de correos para mostrar
        $notified_emails = [];
        
        // Obtener información del usuario actual y del curso
        $current_user = wp_get_current_user();
        global $wpdb;
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT course_id FROM {$wpdb->prefix}sirec_invitation_tokens WHERE token = %s",
            $token
        ));
        $course = get_post($token_data->course_id);
    
        // URL del panel admin para revisar solicitudes
        $admin_url = admin_url('admin.php?page=sirec-applications');
    
        // Preparar el mensaje para los editores
        $editor_subject = 'Nueva solicitud de curso pendiente de revisión';
        $editor_message = sprintf(
            'Hola,<br><br>'.
            'Se ha recibido una nueva solicitud de curso que requiere su revisión:<br><br>'.
            'Solicitante: %s %s<br>'.
            'Correo: %s<br>'.
            'Curso: %s<br><br>'.
            'Para revisar y aprobar/rechazar esta solicitud, haga clic en el siguiente enlace:<br>'.
            '<a href="%s">Revisar solicitud en el panel administrativo</a><br><br>'.
            'Saludos cordiales,<br>'.
            'Sistema SIREC',
            sanitize_text_field($_POST['first_name']),
            sanitize_text_field($_POST['last_name']),
            $current_user->user_email,
            $course->post_title,
            $admin_url
        );
    
        $headers = array('Content-Type: text/html; charset=UTF-8');
    
        // Enviar correos y notificaciones BuddyBoss a los editores
        foreach ($editor_users as $user) {
            $notified_emails[] = $user->user_email;
            
            // Enviar correo electrónico
            wp_mail($user->user_email, $editor_subject, $editor_message, $headers);
            
            // Enviar notificación BuddyBoss
            if (function_exists('bp_notifications_add_notification')) {
                bp_notifications_add_notification(array(
                    'user_id'           => $user->ID,
                    'item_id'           => $course->ID,
                    'secondary_item_id' => $current_user->ID,
                    'component_name'    => 'sirec_courses',
                    'component_action'  => 'new_course_application',
                    'date_notified'     => bp_core_current_time(),
                    'is_new'           => 1,
                    'allow_duplicate'   => true
                ));
    
                // Agregar actividad en BuddyBoss si está activo
                if (function_exists('bp_activity_add')) {
                    bp_activity_add(array(
                        'user_id'      => $user->ID,
                        'action'       => sprintf(
                            'Nueva solicitud de curso pendiente de revisión para "%s"',
                            $course->post_title
                        ),
                        'component'    => 'sirec_courses',
                        'type'         => 'new_course_application',
                        'primary_link' => $admin_url,
                        'item_id'      => $course->ID
                    ));
                }
            }
        }
        
        // Crear mensaje de éxito con los correos
        $success_message = 'Solicitud enviada correctamente.<br>';
        $success_message .= 'Se enviaron notificaciones a los siguientes correos:<br>';
        $success_message .= '<ul>';
        foreach ($notified_emails as $email) {
            $success_message .= '<li>' . esc_html($email) . '</li>';
        }
        $success_message .= '</ul>';
        
        echo '<div class="alert alert-success">' . wp_kses_post($success_message) . '</div>';
    } else {
        echo '<div class="alert alert-error">' . esc_html($result['message']) . '</div>';
    }
}

?>
<style>
.application-form-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 30px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    position: relative;
}

.application-form-background {
    background-image: url('<?php echo plugins_url('assets/images/background.jpg', dirname(__FILE__)); ?>');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    min-height: 100vh;
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #1e73be;
    font-weight: 600;
    font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-group input[type="text"]:focus,
.form-group input[type="date"]:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #1e73be;
    outline: none;
    box-shadow: 0 0 5px rgba(30, 115, 190, 0.2);
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

button[type="submit"] {
    background-color: #1e73be;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s ease;
    display: block;
    margin: 0 auto;
    min-width: 200px;
}

button[type="submit"]:hover {
    background-color: #165b96;
}

.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    font-weight: 500;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .application-form-container {
        margin: 20px auto;
        padding: 20px;
    }
    
    button[type="submit"] {
        width: 100%;
    }
}
</style>

<div class="application-form-background">
    <div class="application-form-container">
        <form method="post" action="">
            <?php wp_nonce_field('sirec_application_nonce', 'application_nonce'); ?>
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            
            <div class="form-group">
                <label for="first_name">Nombre:</label>
                <input type="text" name="first_name" id="first_name" required>
            </div>

            <div class="form-group">
                <label for="last_name">Apellido:</label>
                <input type="text" name="last_name" id="last_name" required>
            </div>

            <div class="form-group">
                <label for="birth_date">Fecha de Nacimiento:</label>
                <input type="date" name="birth_date" id="birth_date" required>
            </div>

            <div class="form-group">
                <label for="birth_country">País de Nacimiento:</label>
                <input type="text" name="birth_country" id="birth_country" required>
            </div>

            <div class="form-group">
                <label for="residence_country">País de Residencia:</label>
                <input type="text" name="residence_country" id="residence_country" required>
            </div>

            <div class="form-group">
                <label for="profession">Profesión:</label>
                <select name="profession" id="profession" required>
                    <option value="">Seleccione...</option>
                    <option value="padre">Padre de familia</option>
                    <option value="estudiante">Estudiante</option>
                    <option value="docente">Docente</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <div class="form-group">
                <label for="participation_reason">Motivo de participación:</label>
                <textarea name="participation_reason" id="participation_reason" required></textarea>
            </div>

            <button type="submit" name="submit_application">Enviar Solicitud</button>
        </form>
    </div>
</div>

<?php
// Función para procesar el formulario
function sirec_process_application_form($post_data) {
    if (!wp_verify_nonce($post_data['application_nonce'], 'sirec_application_nonce')) {
        return array('success' => false, 'message' => 'Error de seguridad');
    }

    global $wpdb;
    $current_user = wp_get_current_user();

    // Verificar el token nuevamente antes de procesar
    $token = sanitize_text_field($post_data['token']);
    $token_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sirec_invitation_tokens 
        WHERE token = %s AND used = 0 AND expires_at > NOW() AND user_id = %d",
        $token,
        $current_user->ID
    ));

    if (!$token_data) {
        return array('success' => false, 'message' => 'Token inválido o expirado');
    }

    // Insertar en la base de datos
    $result = $wpdb->insert(
        $wpdb->prefix . 'course_applications',
        array(
            'user_id' => $current_user->ID,
            'course_id' => $token_data->course_id, // Añadir course_id del token
            'first_name' => sanitize_text_field($post_data['first_name']),
            'last_name' => sanitize_text_field($post_data['last_name']),
            'birth_date' => sanitize_text_field($post_data['birth_date']),
            'birth_country' => sanitize_text_field($post_data['birth_country']),
            'residence_country' => sanitize_text_field($post_data['residence_country']),
            'profession' => sanitize_text_field($post_data['profession']),
            'participation_reason' => sanitize_textarea_field($post_data['participation_reason']),
            'status' => 'pending',
            'submission_date' => current_time('mysql')
        )
    );

    if ($result) {
        // Marcar el token como usado
        $wpdb->update(
            $wpdb->prefix . 'sirec_invitation_tokens',
            array('used' => 1),
            array('token' => $token)
        );
        return array('success' => true, 'message' => 'Solicitud enviada correctamente3');
    } else {
        return array('success' => false, 'message' => 'Error al guardar la solicitud');
    }
}
?>