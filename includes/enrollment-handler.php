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
        if (!class_exists('Eb_Course')) {
            error_log('Edwiser Bridge no está instalado');
            return false;
        }

        try {
            $moodle_course_id = get_post_meta($application->course_id, 'moodle_course_id', true);
            
            $moodle_user_id = get_user_meta($application->user_id, 'moodle_user_id', true);
            
            if (!$moodle_course_id || !$moodle_user_id) {
                error_log('No se encontró el ID de Moodle para el curso o usuario');
                return false;
            }

            $eb_api = new Eb_Connection_Helper();
            $response = $eb_api->enroll_user($moodle_user_id, $moodle_course_id);
            
            if (isset($response['success']) && $response['success']) {
                return true;
            }
            
            error_log('Error al matricular en Moodle: ' . print_r($response, true));
            return false;
            
        } catch (Exception $e) {
            error_log('Error en la matriculación: ' . $e->getMessage());
            return false;
        }
    }
}