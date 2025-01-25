<?php
defined('ABSPATH') || exit;

if (!class_exists('BP_Core_Notification_Abstract')) {
    return;
}

class BP_SIREC_Notification extends BP_Core_Notification_Abstract {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->start();
    }

    public function load() {
        $this->register_notification_group(
            'sirec_courses',
            esc_html__('Invitaciones a Cursos', 'sirec'),
            esc_html__('Notificaciones de SIREC', 'sirec')
        );

        $this->register_notification_type(
            'new_course_invitation',
            esc_html__('Invitación a Curso', 'sirec'),
            esc_html__('Invitaciones a cursos SIREC', 'sirec'),
            'sirec_courses'
        );

        $this->register_notification(
            'sirec_courses',
            'new_course_invitation',
            'new_course_invitation'
        );

        $this->register_notification_filter(
            __('Invitaciones SIREC', 'sirec'),
            array('new_course_invitation'),
            5
        );
    }

    public function format_notification($content, $item_id, $secondary_item_id, $action_item_count, $component_action_name, $component_name, $notification_id, $screen) {
        if ('sirec_courses' === $component_name && 'new_course_invitation' === $component_action_name) {
            // Obtener el token de invitación para este usuario y curso
            global $wpdb;
            $token = $wpdb->get_var($wpdb->prepare(
                "SELECT token FROM {$wpdb->prefix}sirec_invitation_tokens 
                WHERE user_id = %d AND course_id = %d AND used = 0 
                ORDER BY created_at DESC LIMIT 1",
                get_current_user_id(),
                $item_id
            ));
    
            $course_title = get_the_title($item_id);
            // Generar el link del formulario en lugar del link del curso
            $form_url = home_url('/solicitud-curso/?token=' . $token);
            
            $text = sprintf(
                __('Tienes una invitación pendiente para el curso: %s', 'sirec'),
                $course_title
            );
    
            if ($screen == "app_push" || $screen == "web_push") {
                $text = sprintf(
                    __('Nueva invitación pendiente para el curso: %s', 'sirec'),
                    $course_title
                );
            }   
    
            return array(
                'title' => __('Invitación a Curso', 'sirec'),
                'text'  => $text,
                'link'  => $form_url, // Ahora apunta al formulario
            );
        }
    
        return $content;
    }
}