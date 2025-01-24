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
        echo '<div class="alert alert-success">Solicitud enviada correctamente</div>';
    } else {
        echo '<div class="alert alert-error">' . esc_html($result['message']) . '</div>';
    }
}
?>

<form method="post" action="">
    <?php wp_nonce_field('sirec_application_nonce', 'application_nonce'); ?>
    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
    
    <div class="form-group">
        <label for="first_name">Nombre:</label>
        <input type="text" name="first_name" required>
    </div>

    <div class="form-group">
        <label for="last_name">Apellido:</label>
        <input type="text" name="last_name" required>
    </div>

    <div class="form-group">
        <label for="birth_date">Fecha de Nacimiento:</label>
        <input type="date" name="birth_date" required>
    </div>

    <div class="form-group">
        <label for="birth_country">País de Nacimiento:</label>
        <input type="text" name="birth_country" required>
    </div>

    <div class="form-group">
        <label for="residence_country">País de Residencia:</label>
        <input type="text" name="residence_country" required>
    </div>

    <div class="form-group">
        <label for="profession">Profesión:</label>
        <select name="profession" required>
            <option value="">Seleccione...</option>
            <option value="padre">Padre de familia</option>
            <option value="estudiante">Estudiante</option>
            <option value="docente">Docente</option>
            <option value="otro">Otro</option>
        </select>
    </div>

    <div class="form-group">
        <label for="participation_reason">Motivo de participación:</label>
        <textarea name="participation_reason" required></textarea>
    </div>

    <button type="submit" name="submit_application">Enviar Solicitud</button>
</form>

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
        return array('success' => true, 'message' => 'Solicitud enviada correctamente');
    } else {
        return array('success' => false, 'message' => 'Error al guardar la solicitud');
    }
}
?>