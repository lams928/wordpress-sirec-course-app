jQuery(document).ready(function($) {

    $('.approve-application').on('click', function() {
        const button = $(this);
        const applicationId = button.data('id');
        const nonce = button.data('nonce');
        
        if (confirm('¿Estás seguro de que deseas aprobar esta solicitud?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sirec_approve_application',
                    application_id: applicationId,
                    nonce: nonce
                },
                beforeSend: function() {
                    button.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('Error al procesar la solicitud');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        }
    });
    
    $('.reject-application').on('click', function() {
        const button = $(this);
        const applicationId = button.data('id');
        const nonce = button.data('nonce');
        
        if (confirm('¿Estás seguro de que deseas rechazar esta solicitud?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sirec_reject_application',
                    application_id: applicationId,
                    nonce: nonce
                },
                beforeSend: function() {
                    button.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('Error al procesar la solicitud');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        }
    });
    
    function loadApplications(status = 'all', page = 1) {
        $.ajax({
            url: sirecAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_get_applications',
                nonce: sirecAjax.nonce,
                status: status,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    $('#applications-list').html(response.data.html);
                }
            }
        });
    }
    
    $(document).on('click', '.application-action', function(e) {
        e.preventDefault();
        
        const applicationId = $(this).data('id');
        const decision = $(this).data('action');
        const notes = prompt('Agregar notas (opcional):');
        
        $.ajax({
            url: sirecAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_send_invitations',
                nonce: sirecAjax.nonce,
                course_id: courseId,
                selected_users: selectedUsers,
                message: message
            },
            success: function(response) {
                if(response.success) {
                    let mensaje = response.data.message;
                    if(response.data.errors && response.data.errors.length > 0) {
                        mensaje += '\n\nDetalles de errores:\n' + response.data.errors.join('\n');
                    }
                    $results.html('<div class="notice notice-info"><p>' + mensaje + '</p></div>');
                } else {
                    $results.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
                console.log('Respuesta completa:', response); 
            },
            error: function(xhr, status, error) {
                console.error('Error Ajax:', error);
                console.log('Estado:', status);
                console.log('Respuesta:', xhr.responseText);
                $results.html('<div class="notice notice-error"><p>Error en la solicitud: ' + error + '</p></div>');
            }
        });
    });
    
    $('#filter-submit').on('click', function() {
        const status = $('#filter-status').val();
        loadApplications(status);
    });
    
    loadApplications();
});