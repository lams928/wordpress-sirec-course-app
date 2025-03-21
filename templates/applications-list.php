<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gestión de Solicitudes</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="filter-status">
                <option value="all">Todos los estados</option>
                <option value="pending">Pendientes</option>
                <option value="approved">Aprobados</option>
                <option value="rejected">Rechazados</option>
            </select>
            <button class="button" id="filter-submit">Filtrar</button>
        </div>
    </div>
    
    <?php
    global $wpdb;
    $applications = $wpdb->get_results("
        SELECT a.*, p.post_title as course_name, u.display_name as user_name
        FROM {$wpdb->prefix}course_applications a
        LEFT JOIN {$wpdb->posts} p ON a.course_id = p.ID
        LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
        ORDER BY a.submission_date DESC
    ");
    ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Curso</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>País Nacimiento</th>
                <th>País Residencia</th>
                <th>Profesión</th>
                <th>Motivo de Participación</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $application): ?>
            <tr>
                <td><?php echo esc_html($application->course_name); ?></td>
                <td><?php echo esc_html($application->first_name); ?></td>
                <td><?php echo esc_html($application->last_name); ?></td>
                <td><?php echo esc_html($application->birth_country); ?></td>
                <td><?php echo esc_html($application->residence_country); ?></td>
                <td><?php echo esc_html($application->profession); ?></td>
                <td><?php echo esc_html($application->participation_reason); ?></td>
                <td><?php echo esc_html($application->submission_date); ?></td>
                <td>
                    <?php 
                    $status = $application->status;
                    $status_text = '';
                    switch($status) {
                        case 'pending':
                            $status_text = 'Pendiente';
                            break;
                        case 'approved':
                            $status_text = 'Aprobado';
                            break;
                        case 'rejected':
                            $status_text = 'Rechazado';
                            break;
                    }
                    echo '<span class="sirec-status-' . esc_attr($status) . '">' . esc_html($status_text) . '</span>';
                    ?>
                </td>
                <td>
                    <?php if($application->status === 'pending'): ?>
                        <button class="button button-primary approve-application" 
                                data-id="<?php echo esc_attr($application->id); ?>"
                                data-nonce="<?php echo wp_create_nonce('sirec_application_action'); ?>">
                            Aprobar
                        </button>
                        <button class="button button-secondary reject-application"
                                data-id="<?php echo esc_attr($application->id); ?>"
                                data-nonce="<?php echo wp_create_nonce('sirec_application_action'); ?>">
                            Rechazar
                        </button>
                    <?php else: ?>
                        <?php echo esc_html(ucfirst($application->status)); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>