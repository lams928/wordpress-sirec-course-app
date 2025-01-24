
<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_die('Debes iniciar sesión para acceder a este formulario. <a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">Iniciar sesión</a>');
}

$current_user = wp_get_current_user();


// Verify token
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

?>

<div class="sirec-application-form">
    <form id="course-application-form" method="post">
        <?php wp_nonce_field('sirec_application_nonce', 'application_nonce'); ?>
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
        
        <div class="form-field">
            <label for="first_name">Nombre</label>
            <input type="text" id="first_name" name="first_name" required>
        </div>
        
        <div class="form-field">
            <label for="last_name">Apellido</label>
            <input type="text" id="last_name" name="last_name" required>
        </div>
        
        <div class="form-field">
            <label for="birth_date">Fecha de Nacimiento</label>
            <input type="date" id="birth_date" name="birth_date" required>
        </div>
        
        <div class="form-field">
            <label for="birth_country">País de Nacimiento</label>
            <input type="text" id="birth_country" name="birth_country" required>
        </div>
        
        <div class="form-field">
            <label for="residence_country">País de Residencia</label>
            <input type="text" id="residence_country" name="residence_country" required>
        </div>
        
        <div class="form-field">
            <label for="participation_reason">Motivo de participación</label>
            <textarea id="participation_reason" name="participation_reason" required></textarea>
        </div>
        
        <div class="form-field">
            <label for="profession">Profesión</label>
            <select id="profession" name="profession" required>
                <option value="">Seleccione una opción...</option>
                <option value="padre">Padre de familia</option>
                <option value="estudiante">Estudiante</option>
                <option value="docente">Docente</option>
                <option value="otro">Otro</option>
            </select>
        </div>
        
        <div class="submit-button">
            <input type="submit" value="Enviar Solicitud">
        </div>
    </form>
</div>