<?php
/*
Plugin Name: SIREC Course Applications
Description: Sistema de gestión de solicitudes de cursos para SIREC
Version: 1.0
Author: Plugin SIREC 
*/

if (!defined('ABSPATH')) exit;

define('SIREC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIREC_PLUGIN_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'sirec_activate_plugin');
add_action('init', 'sirec_register_custom_endpoint');

function sirec_activate_plugin() {
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

    $tokens_table = $wpdb->prefix . 'sirec_invitation_tokens';
    $sql = "CREATE TABLE IF NOT EXISTS $tokens_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        token varchar(32) NOT NULL,
        user_id bigint(20) NOT NULL,
        course_id bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime NOT NULL,
        used tinyint(1) DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY token (token)
    ) $charset_collate;";
    
    dbDelta($sql);

    $role = get_role('editor_iiiccab');
    if($role) {
        $role->add_cap('edit_iiiccab');
    } else {
        add_role('editor_iiiccab', 'Editor IIICCAB', array(
            'read' => true,
            'edit_iiiccab' => true
        ));
    }
}

require_once SIREC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once SIREC_PLUGIN_DIR . 'includes/applications-list.php';
require_once SIREC_PLUGIN_DIR . 'includes/form-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/enrollment-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/notifications.php';
require_once SIREC_PLUGIN_DIR . 'includes/invitation-handler.php';
require_once SIREC_PLUGIN_DIR . 'includes/class-bp-sirec-notification.php';

add_action('plugins_loaded', function() {
    if (!sirec_check_dependencies()) {
        return;
    }
    
    require_once SIREC_PLUGIN_DIR . 'includes/admin-menu.php';
    require_once SIREC_PLUGIN_DIR . 'includes/applications-list.php';
    require_once SIREC_PLUGIN_DIR . 'includes/form-handler.php';
    require_once SIREC_PLUGIN_DIR . 'includes/enrollment-handler.php';
    require_once SIREC_PLUGIN_DIR . 'includes/notifications.php';
    require_once SIREC_PLUGIN_DIR . 'includes/invitation-handler.php';
    require_once SIREC_PLUGIN_DIR . 'includes/class-bp-sirec-notification.php';
    
    add_action('wp_ajax_sirec_get_courses', function() {
        check_ajax_referer('sirec_nonce', 'nonce');
        
        $courses = sirec_get_edwiser_courses();
        $formatted_courses = array_map(function($course) {
            return array(
                'id' => $course->ID,
                'title' => $course->post_title
            );
        }, $courses);
        
        wp_send_json_success($formatted_courses);
    });
    
}, 20);

function sirec_check_dependencies() {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    
    if (!is_plugin_active('edwiser-bridge/edwiser-bridge.php')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>El plugin SIREC Course Applications requiere que Edwiser Bridge esté instalado y activado.</p></div>';
        });
        return false;
    }
    return true;
}
function sirec_get_edwiser_courses() {
    $args = array(
        'post_type' => 'eb_course',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    return get_posts($args);
}


add_action('admin_enqueue_scripts', 'sirec_admin_scripts');



function sirec_admin_scripts($hook) {
    if (!in_array($hook, ['toplevel_page_sirec-applications', 'solicitudes-sirec_page_sirec-new-invitation'])) {
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

    wp_enqueue_style('select2', 
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', 
        [], 
        '4.1.0'
    );
    
    wp_enqueue_script('select2', 
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 
        ['jquery'], 
        '4.1.0', 
        true
    );

    wp_localize_script('sirec-admin-script', 'sirecAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sirec_nonce')
    ]);
}
add_action('plugins_loaded', 'sirec_check_buddyboss');

function sirec_check_buddyboss() {
    if (function_exists('buddypress')) {
        add_action('bp_setup_components', 'sirec_register_buddyboss_component');
    }
}

add_action('bp_setup_components', 'sirec_register_buddyboss_component', 10);
add_action('bp_init', function() {
    if (class_exists('BP_SIREC_Notification')) {
        BP_SIREC_Notification::instance();
    }
});


function sirec_register_buddyboss_component() {
    if (function_exists('bp_notifications_register_component')) {
        bp_notifications_register_component('sirec_courses');
    }
}

function sirec_format_buddyboss_notifications($action, $item_id, $secondary_item_id, $total_items, $format = 'string') {
    if ($action !== 'new_course_invitation') {
        return $action;
    }

    $course_title = get_the_title($item_id);
    $course_link = get_permalink($item_id);
    
    if ('string' === $format) {
        $return = sprintf(
            __('Has sido invitado al curso: <a href="%s">%s</a>', 'sirec'),
            esc_url($course_link),
            esc_html($course_title)
        );
    } else {
        $return = array(
            'text' => sprintf(__('Has sido invitado al curso: %s', 'sirec'), $course_title),
            'link' => $course_link
        );
    }

    return $return;
}

// In sirec-course-applications.php



/*Shortocde form*/
function sirec_application_form_shortcode($atts) {
    // Verificar si el usuario está logueado
    if (!is_user_logged_in()) {
        return '<div class="alert alert-error">Debes iniciar sesión para acceder a este formulario. <a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">Iniciar sesión</a></div>';
    }

    // Obtener el token
    $token = get_query_var('token') ? get_query_var('token') : (isset($_GET['token']) ? $_GET['token'] : '');
    
    if (empty($token)) {
        return '<div class="alert alert-error">Token no proporcionado. Acceso denegado.</div>';
    }

    // Verificar el token y el usuario
    global $wpdb;
    $token_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sirec_invitation_tokens 
        WHERE token = %s AND used = 0 AND expires_at > NOW() AND user_id = %d",
        $token,
        get_current_user_id()
    ));

    if (!$token_data) {
        return '<div class="alert alert-error">Token inválido o no autorizado para este usuario.</div>';
    }

    $current_user_id = get_current_user_id();
    if ($current_user_id !== intval($token_data->user_id)) {
        return '<div class="alert alert-error">No tienes permiso para acceder a esta invitación. Esta invitación fue enviada a otro usuario.</div>';
    }

    // Procesar el formulario si se envió
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
        if (!wp_verify_nonce($_POST['application_nonce'], 'sirec_application_nonce')) {
            return '<div class="alert alert-error">Error de seguridad. Por favor, recarga la página.</div>';
        }

        $current_user = wp_get_current_user();
        
        $application_data = array(
            'user_id' => $current_user->ID,
            'course_id' => $token_data->course_id,
            'status' => 'pending',
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'birth_date' => sanitize_text_field($_POST['birth_date']),
            'birth_country' => sanitize_text_field($_POST['birth_country']),
            'residence_country' => sanitize_text_field($_POST['residence_country']),
            'profession' => sanitize_text_field($_POST['profession']),
            'participation_reason' => sanitize_textarea_field($_POST['participation_reason']),
            'submission_date' => current_time('mysql')
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'course_applications',
            $application_data
        );

        if ($result) {
            // Marcar el token como usado
            $wpdb->update(
                $wpdb->prefix . 'sirec_invitation_tokens',
                array('used' => 1),
                array('token' => $token)
            );

            // Notificar a los editores
            if (function_exists('notify_editors_of_new_application')) {
                notify_editors_of_new_application($wpdb->insert_id, $token_data->course_id);
            }

            return '<div class="alert alert-success">Solicitud enviada correctamente.</div>';
        } else {
            return '<div class="alert alert-error">Error al guardar la solicitud. Por favor, intenta nuevamente.</div>';
        }
    }

    ob_start();
    ?>
    <div class="sirec-application-form">
        <form method="post" action="" class="sirec-form">
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

            <div class="form-group">
                <button type="submit" name="submit_application" class="sirec-submit-btn">
                    Enviar Solicitud
                </button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('sirec_application_form', 'sirec_application_form_shortcode');

add_action('wp_enqueue_scripts', 'sirec_enqueue_form_assets');

function sirec_enqueue_form_assets() {
    if (has_shortcode(get_post()->post_content, 'sirec_application_form') || 
        get_query_var('pagename') === 'invitacion-curso') {
        wp_enqueue_style('sirec-form-style', SIREC_PLUGIN_URL . 'assets/css/form-style.css');
        wp_enqueue_script('sirec-form-script', SIREC_PLUGIN_URL . 'assets/js/application-form.js', array('jquery'), '1.0', true);
        
        wp_localize_script('sirec-form-script', 'sirecAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sirec_application_nonce')
        ));
    }
}

function sirec_register_custom_endpoint() {
    // Crear la página si no existe
    $page = get_page_by_path('invitacion-curso');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title'    => 'Invitación Curso',
            'post_name'     => 'invitacion-curso',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '[sirec_application_form]'
        ));
    }

    // Agregar reglas de reescritura
    add_rewrite_rule(
        '^invitacion-curso/?$',
        'index.php?pagename=invitacion-curso',
        'top'
    );

    add_rewrite_rule(
        '^invitacion-curso/([^/]+)/?$',
        'index.php?pagename=invitacion-curso&token=$matches[1]',
        'top'
    );

    flush_rewrite_rules();
}

function sirec_register_query_vars($vars) {
    $vars[] = 'token';
    return $vars;
}
add_filter('query_vars', 'sirec_register_query_vars');


function sirec_handle_custom_page($template) {
    if (get_query_var('pagename') === 'invitacion-curso') {
        // Verificar si existe la página
        $page = get_page_by_path('invitacion-curso');
        if (!$page) {
            return $template; // Retornar template normal si la página no existe
        }

        // Obtener el token
        $token = get_query_var('token');
        if (empty($token)) {
            $token = isset($_GET['token']) ? $_GET['token'] : '';
        }

        // Si no hay token, mostrar la página normal
        if (empty($token)) {
            return get_page_template();
        }

        // Verificar el token
        global $wpdb;
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sirec_invitation_tokens 
            WHERE token = %s AND used = 0 AND expires_at > NOW()",
            $token
        ));

        if (!$token_data) {
            return get_page_template();
        }

        // Si el usuario no está logueado, redirigir al login
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(add_query_arg('token', $token, get_permalink($page->ID))));
            exit;
        }

        // Verificar que el token corresponda al usuario actual
        if (get_current_user_id() !== intval($token_data->user_id)) {
            wp_redirect(home_url());
            exit;
        }

        return get_page_template();
    }
    return $template;
}
add_filter('template_include', 'sirec_handle_custom_page');