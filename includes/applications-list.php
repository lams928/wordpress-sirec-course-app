<?php
if (!defined('ABSPATH')) exit;

class SIREC_Applications_List {
    public static function get_applications($status = 'all', $per_page = 10, $page = 1) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'course_applications';
        
        $where = '';
        if ($status !== 'all') {
            $where = $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $offset = ($page - 1) * $per_page;
        
        $applications = $wpdb->get_results(
            "SELECT a.*, c.post_title as course_name 
            FROM $table_name a 
            LEFT JOIN {$wpdb->posts} c ON a.course_id = c.ID 
            WHERE c.post_type = 'eb_course' 
            $where 
            ORDER BY submission_date DESC 
            LIMIT $per_page OFFSET $offset"
        );
        
        return $applications;
    }
    
    public static function get_total_applications($status = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'course_applications';
        
        $where = '';
        if ($status !== 'all') {
            $where = $wpdb->prepare(" AND a.status = %s", $status);
        }
        
        return $wpdb->get_var(
            "SELECT COUNT(*) 
            FROM $table_name a 
            LEFT JOIN {$wpdb->posts} c ON a.course_id = c.ID 
            WHERE c.post_type = 'eb_course' $where"
        );
    }
}