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
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Solicitante</th>
                <th>Curso</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="applications-list">
        </tbody>
    </table>
</div>