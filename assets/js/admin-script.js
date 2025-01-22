jQuery(document).ready(function($) {
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
    
    // Manejador de aprobación/rechazo
    $(document).on('click', '.application-action', function(e) {
        e.preventDefault();
        
        const applicationId = $(this).data('id');
        const decision = $(this).data('action');
        const notes = prompt('Agregar notas (opcional):');
        
        $.ajax({
            url: sirecAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sirec_handle_application',
                nonce: sirecAjax.nonce,
                application_id: applicationId,
                decision: decision,
                notes: notes
            },
            success: function(response) {
                if (response.success) {
                    alert('Solicitud procesada correctamente');
                    loadApplications();
                } else {
                    alert('Error al procesar la solicitud');
                }
            }
        });
    });
    
    // Filtrado
    $('#filter-submit').on('click', function() {
        const status = $('#filter-status').val();
        loadApplications(status);
    });
    
    // Carga inicial
    loadApplications();
});