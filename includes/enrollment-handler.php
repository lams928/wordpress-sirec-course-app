<?php
if (!defined('ABSPATH')) exit;

class SIREC_Enrollment_Handler {
    public function process_enrollment($application) {
        // Matricular en LearnDash
        $learndash_success = $this->enroll_in_learndash($application);
        
        // Matricular en Moodle
        $moodle_success = $this->enroll_in_moodle($application);
        
        return [
            'learndash' => $learndash_success,
            'moodle' => $moodle_success
        ];
    }
    
    private function enroll_in_learndash($application) {
        if (function_exists('ld_update_course_access')) {
            return ld_update_course_access($application->user_id, $application->course_id);
        }
        return false;
    }
    
    private function enroll_in_moodle($application) {
        // Implementar la lógica de matriculación en Moodle
        // usando la API REST de Moodle
        return true;
    }
}