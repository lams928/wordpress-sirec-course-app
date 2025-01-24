<?php
if (!defined('ABSPATH')) exit;

class SIREC_Enrollment_Handler {
    private $api_url = 'https://sirec-aulas.edupan.dev/webservice/rest/server.php';
    private $wstoken = 'da37c0fddbaa3b114f31c77274610ada';

    public function process_enrollment($application) {
        $moodle_success = $this->enroll_in_moodle($application);
        
        return [
            'moodle' => $moodle_success
        ];
    }
    
    private function enroll_in_moodle($application) {
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
                error_log('ID de usuario Moodle no encontrado para el usuario WordPress ID: ' . $application->user_id);
                return false;
            }

            // Preparar datos para la API
            $body = [
                'wstoken' => $this->wstoken,
                'wsfunction' => 'local_wsmoodlehl_enrol_user',
                'enrolments[0][userid]' => $moodle_user_id,
                'enrolments[0][courseid]' => $moodle_course_id,
                'enrolments[0][roleid]' => 5 // Role ID 5 para estudiante
            ];

            // Realizar la petición a la API
            $response = wp_remote_post($this->api_url . '?moodlewsrestformat=json', [
                'body' => $body,
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                error_log('Error en la petición a Moodle: ' . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);

            // Verificar si la respuesta es exitosa
            if (isset($result['exception'])) {
                error_log('Error de Moodle API: ' . print_r($result, true));
                return false;
            }

            // Actualizar meta del usuario para registrar la matriculación
            update_user_meta($application->user_id, 'enrolled_course_' . $application->course_id, true);
            return true;
            
        } catch (Exception $e) {
            error_log('Excepción en la matriculación: ' . $e->getMessage());
            return false;
        }
    }
}