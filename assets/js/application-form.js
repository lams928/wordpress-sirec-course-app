jQuery(document).ready(function($) {
    $('#course-application-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        
        // Deshabilitar el botón mientras se procesa
        $submitButton.prop('disabled', true);
        
        const formData = new FormData(this);
        formData.append('action', 'sirec_submit_application');
        formData.append('nonce', $('#application_nonce').val());
        
        $.ajax({
            url: sirecAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Mostrar mensaje de éxito
                    $form.html('<div class="sirec-success-message">' + 
                        '<p>¡Tu solicitud ha sido enviada correctamente!</p>' +
                        '</div>');
                } else {
                    // Mostrar mensaje de error
                    $form.prepend('<div class="sirec-error-message">' +
                        '<p>Error: ' + (response.data || 'Ha ocurrido un error al enviar la solicitud.') + '</p>' +
                        '</div>');
                    $submitButton.prop('disabled', false);
                }
            },
            error: function() {
                // Mostrar mensaje de error genérico
                $form.prepend('<div class="sirec-error-message">' +
                    '<p>Error: Ha ocurrido un error al procesar la solicitud. Por favor, intenta nuevamente.</p>' +
                    '</div>');
                $submitButton.prop('disabled', false);
            }
        });
    });
});