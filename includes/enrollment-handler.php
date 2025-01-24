<?php
if (!defined('ABSPATH')) exit;

class SIREC_Enrollment_Handler {
    public function process_enrollment($application) {
        $moodle_success = $this->enroll_in_moodle($application);
        
        return [
            'moodle' => $moodle_success
        ];
    }
    
    private function enroll_in_moodle($application) {
        if (!class_exists('Eb_Connection_Helper')) {
            error_log('Edwiser Bridge no está instalado correctamente');
            return false;
        }

        try {
            // Obtener el ID del curso en Moodle
            $moodle_course_id = get_post_meta($application->course_id, 'moodle_course_id', true);
            if (!$moodle_course_id) {
                error_log('ID de curso Moodle no encontrado para el curso WordPress ID: ' . $application->course_id);
                return false;
            }
            
            // Obtener el ID del usuario en Moodle
            $moodle_user_id = get_user_meta($application->user_id, 'moodle_user_id', true);
            if (!$moodle_user_id) {
                // Intentar sincronizar el usuario si no existe
                $eb_user_sync = new Eb_User_Manager();
                $sync_result = $eb_user_sync->sync_wordpress_user_with_moodle($application->user_id);
                
                if ($sync_result) {
                    $moodle_user_id = get_user_meta($application->user_id, 'moodle_user_id', true);
                }
                
                if (!$moodle_user_id) {
                    error_log('ID de usuario Moodle no encontrado para el usuario WordPress ID: ' . $application->user_id);
                    return false;
                }
            }

            // Crear instancia del helper de conexión
            $eb_api = new Eb_Connection_Helper();
            
            // Verificar si el usuario ya está matriculado
            $is_enrolled = $eb_api->get_enrollment_status($moodle_user_id, $moodle_course_id);
            if ($is_enrolled) {
                error_log('Usuario ya matriculado en el curso');
                return true;
            }

            // Intentar matricular al usuario
            $response = $eb_api->enroll_user($moodle_user_id, $moodle_course_id);
            
            if (is_wp_error($response)) {
                error_log('Error WP al matricular: ' . $response->get_error_message());
                return false;
            }
            
            if (isset($response['success']) && $response['success']) {
                // Actualizar meta del usuario para registrar la matriculación
                update_user_meta($application->user_id, 'enrolled_course_' . $application->course_id, true);
                return true;
            }
            
            error_log('Error al matricular en Moodle. Respuesta: ' . print_r($response, true));
            return false;
            
        } catch (Exception $e) {
            error_log('Excepción en la matriculación: ' . $e->getMessage());
            return false;
        }
    }
}