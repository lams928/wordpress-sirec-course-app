<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Nueva Invitación a Curso</h1>
    
    <div class="sirec-invitation-form">
        <form id="sirec-send-invitation" method="post">
            <?php wp_nonce_field('sirec_invitation_nonce', 'invitation_nonce'); ?>
            
            <table class="form-table">
            <tr>
                <th><label for="course_id">Seleccionar Curso</label></th>
                <td>
                <select name="course_id" id="course_id" required>
                    <option value="">Seleccione un curso...</option>
                    <?php
                    $courses = sirec_get_edwiser_courses();
                    if (!empty($courses)) {
                        foreach($courses as $course) {
                            printf(
                                '<option value="%d">%s</option>',
                                $course->ID,
                                esc_html($course->post_title)
                            );
                        }
                    } else {
                        echo '<option value="">No hay cursos disponibles</option>';
                    }
                    ?>
                </select>
                </td>
            </tr>

            <tr>
                <th><label for="selected_users">Seleccionar Usuarios</label></th>
                <td>
                    <select name="selected_users[]" id="selected_users" multiple="multiple" required style="width: 100%;">
                        <?php
                        $users = get_users(['role__not_in' => ['administrator']]);
                        foreach($users as $user) {
                            printf(
                                '<option value="%d">%s (%s)</option>',
                                $user->ID,
                                esc_html($user->display_name),
                                esc_html($user->user_email)
                            );
                        }
                        ?>
                    </select>
                    <p class="description">Mantén presionada la tecla Ctrl (Cmd en Mac) para seleccionar múltiples usuarios</p>
                </td>
            </tr>
                
                <tr>
                    <th><label for="invitation_message">Mensaje Personalizado</label></th>
                    <td>
                        <textarea name="invitation_message" id="invitation_message" rows="5" cols="50"
                            placeholder="Mensaje opcional que se incluirá en la invitación..."></textarea>
                    </td>
                </tr>
            </table>
            
            <div class="submit-button">
                <input type="submit" class="button button-primary" value="Enviar Invitaciones">
                <span class="spinner" style="float:none;"></span>
            </div>
        </form>
        
        <div id="invitation-results" style="margin-top:20px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#selected_users').select2({
        placeholder: 'Selecciona uno o varios usuarios...',
        width: '100%'
    });

    $('#sirec-send-invitation').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $spinner = $form.find('.spinner');
        const $submit = $form.find(':submit');
        const $results = $('#invitation-results');
        
        $spinner.addClass('is-active');
        $submit.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_send_invitations',
                nonce: $('#invitation_nonce').val(),
                course_id: $('#course_id').val(),
                message: $('#invitation_message').val(),
                selected_users: $('#selected_users').val() 
            },
            success: function(response) {
                if(response.success) {
                    let resultHtml = '<div class="notice notice-info">';
                    
                    resultHtml += '<p><strong>Notificaciones:</strong><br>';
                    resultHtml += response.data.notification_stats.message + '</p>';
                    
                    resultHtml += '<p><strong>Correos Electrónicos:</strong><br>';
                    resultHtml += response.data.email_stats.message + '</p>';
                    
                    resultHtml += '</div>';
                    
                    $('#invitation-results').html(resultHtml);
                } else {
                    $('#invitation-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $results.html('<div class="notice notice-error"><p>Error al enviar invitaciones</p></div>');
            },
            complete: function() {
                $spinner.removeClass('is-active');
                $submit.prop('disabled', false);
            }
        });
    });
});
</script>