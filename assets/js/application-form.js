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
                    // Alerta simple de éxito
                    alert('¡Solicitud enviada exitosamente!');
                    // Limpiar formulario
                    $form[0].reset();
                    // Redireccionar después de 2 segundos
                    setTimeout(function() {
                        window.location.href = '/'; // Puedes cambiar la URL de redirección
                    }, 2000);
                } else {
                    // Alerta simple de error
                    alert('Error: ' + (response.data || 'Ha ocurrido un error al enviar la solicitud.'));
                    $submitButton.prop('disabled', false);
                }
            },
            error: function() {
                // Alerta simple de error de sistema
                alert('Error: Ha ocurrido un error en el sistema. Por favor, intenta nuevamente.');
                $submitButton.prop('disabled', false);
            }
        });
    });
});