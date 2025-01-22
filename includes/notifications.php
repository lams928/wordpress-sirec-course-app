<?php
if (!defined('ABSPATH')) exit;

class SIREC_Notifications {
    public static function send_application_notification($application_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'course_applications';
        
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $application_id
        ));
        
        $user = get_userdata($application->user_id);
        $course = get_post($application->course_id);
        
        $subject = $status === 'approved' 
            ? 'Tu solicitud ha sido aprobada' 
            : 'Actualización sobre tu solicitud';
        
        $message = self::get_email_template($status, [
            'user_name' => $user->display_name,
            'course_name' => $course->post_title
        ]);
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    private static function get_email_template($status, $data) {
        ob_start();
        include SIREC_PLUGIN_DIR . 'templates/emails/' . $status . '.php';
        return ob_get_clean();
    }
}