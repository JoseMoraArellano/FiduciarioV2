<?php
session_start();

// Si necesitas verificar autenticación, hazlo aquí
// if (!isset($_SESSION['user_id'])) {
//     header('Location: /login.php');
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Sincronización Banxico</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Tailwind CSS (opcional, ya tienes Bootstrap) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top: 4px solid #3498db;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .log-container {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 5px;
            height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .indicator-card {
            transition: all 0.3s;
        }
        
        .indicator-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    
<div class="container-fluid py-4" x-data="banxicoApp()">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h1 class="h2 fw-bold text-primary">
                <i class="fas fa-chart-line me-2"></i>
                Panel de Sincronización Banxico
            </h1>
            <p class="text-muted">Gestión de indicadores financieros de México</p>
        </div>
    </div>
    
    <!-- Alertas -->
    <div class="row mb-3" x-show="alerta.mostrar" x-transition>
        <div class="col">
            <div :class="`alert alert-${alerta.tipo} alert-dismissible fade show`" role="alert">
                <strong x-text="alerta.titulo"></strong> <span x-text="alerta.mensaje"></span>
                <button type="button" class="btn-close" @click="alerta.mostrar = false"></button>
            </div>
        </div>
    </div>
    
    <!-- Controles principales -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-sync me-2"></i>
                        Sincronización de Datos Actuales
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Obtiene los datos más recientes de cada indicador</p>
                    
                    <div class="row g-2">
                        <div class="col-md-8">
                            <select class="form-select" x-model="indicadorSeleccionado">
                                <option value="todos">Todos los indicadores</option>
                                <option value="tiie">TIIE a 28 días</option>
                                <option value="tipoCambio">Tipo de Cambio</option>
                                <option value="inpc">INPC</option>
                                <option value="cpp">CPP</option>
                                <option value="udis">UDIS</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button 
                                class="btn btn-primary w-100"
                                @click="sincronizarDiario()"
                                :disabled="procesando">
                                <i class="fas fa-download me-1"></i>
                                <span x-show="!procesando">Sincronizar</span>
                                <span x-show="procesando">Procesando...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Carga de Datos Históricos
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Carga completa de datos históricos (puede tardar hasta 30 min)</p>
                    
                    <div class="row g-2">
                        <div class="col-md-8">
                            <select class="form-select" x-model="indicadorHistorico">
                                <option value="todos">Todos los históricos</option>
                                <option value="tiie">TIIE (desde 1993)</option>
                                <option value="tdc">Tipo Cambio (desde 2021)</option>
                                <option value="inpc">INPC (desde 1969)</option>
                                <option value="cpp">CPP (desde 1975)</option>
                                <option value="udis">UDIS (desde 1995)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button 
                                class="btn btn-warning w-100 text-dark"
                                @click="cargarHistorico()"
                                :disabled="procesando">
                                <i class="fas fa-database me-1"></i>
                                <span x-show="!procesando">Cargar</span>
                                <span x-show="procesando">Cargando...</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Opciones avanzadas -->
                    <div class="mt-3">
                        <a href="#" @click.prevent="mostrarAvanzadas = !mostrarAvanzadas" class="text-decoration-none">
                            <small>
                                <i :class="mostrarAvanzadas ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                                Opciones avanzadas
                            </small>
                        </a>
                        
                        <div x-show="mostrarAvanzadas" x-transition class="mt-2">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small">Fecha inicio:</label>
                                    <input type="date" class="form-control form-control-sm" x-model="fechaInicio">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Fecha fin:</label>
                                    <input type="date" class="form-control form-control-sm" x-model="fechaFin">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Estadísticas de Indicadores
                    </h5>
                    <button class="btn btn-sm btn-light" @click="obtenerEstadisticas()">
                        <i class="fas fa-refresh"></i> Actualizar
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <template x-for="(stats, key) in estadisticas" :key="key">
                            <div class="col-md-4 col-lg-2">
                                <div class="card indicator-card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-primary" x-text="stats.nombre"></h6>
                                        <div class="mb-2">
                                            <small class="text-muted">Total registros:</small>
                                            <p class="h5 mb-0" x-text="stats.total_registros || '0'"></p>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted">Último valor:</small>
                                            <p class="h6 mb-0" x-text="stats.ultimo_dato ? stats.ultimo_dato.toFixed(4) : 'N/A'"></p>
                                        </div>
                                        <div>
                                            <small class="text-muted">Fecha:</small>
                                            <p class="small mb-0" x-text="stats.fecha_ultimo || 'N/A'"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <div x-show="Object.keys(estadisticas).length === 0" class="text-center py-4">
                        <p class="text-muted">No hay estadísticas disponibles. Haz clic en "Actualizar" para cargarlas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Visor de Logs -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-terminal me-2"></i>
                        Registro de Actividad (Log)
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-light me-2" @click="verLog()">
                            <i class="fas fa-eye"></i> Ver Log
                        </button>
                        <button class="btn btn-sm btn-danger" @click="limpiarLog()">
                            <i class="fas fa-trash"></i> Limpiar
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="log-container" x-html="logContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loader Modal -->
    <div x-show="procesando" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background-color: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="bg-white rounded p-4 text-center">
            <div class="loader mb-3"></div>
            <p class="mb-0" x-text="mensajeProcesando"></p>
        </div>
    </div>
    
</div>

<script>
function banxicoApp() {
    return {
        procesando: false,
        mensajeProcesando: 'Procesando...',
        indicadorSeleccionado: 'todos',
        indicadorHistorico: 'todos',
        mostrarAvanzadas: false,
        fechaInicio: '',
        fechaFin: '',
        estadisticas: {},
        logContent: 'Log vacío. Ejecuta una acción para ver el registro.',
        alerta: {
            mostrar: false,
            tipo: 'info',
            titulo: '',
            mensaje: ''
        },
        
        init() {
            this.obtenerEstadisticas();
        },
        
        mostrarAlerta(tipo, titulo, mensaje) {
            this.alerta = {
                mostrar: true,
                tipo: tipo,
                titulo: titulo,
                mensaje: mensaje
            };
            
            setTimeout(() => {
                this.alerta.mostrar = false;
            }, 5000);
        },
        
        async sincronizarDiario() {
            this.procesando = true;
            this.mensajeProcesando = 'Sincronizando datos actuales...';
            
            try {
                const formData = new FormData();
                formData.append('accion', 'sincronizar_diario');
                formData.append('indicador', this.indicadorSeleccionado);
                
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.mostrarAlerta('success', '¡Éxito!', data.mensaje);
                    this.obtenerEstadisticas();
                    this.verLog();
                } else {
                    this.mostrarAlerta('danger', 'Error', data.mensaje);
                }
                
            } catch (error) {
                this.mostrarAlerta('danger', 'Error', 'Error de conexión: ' + error.message);
            } finally {
                this.procesando = false;
            }
        },
        
        async cargarHistorico() {
            if (!confirm('La carga histórica puede tardar hasta 30 minutos. ¿Deseas continuar?')) {
                return;
            }
            
            this.procesando = true;
            this.mensajeProcesando = 'Cargando datos históricos... Esto puede tardar varios minutos.';
            
            try {
                const formData = new FormData();
                formData.append('accion', 'sincronizar_historico');
                formData.append('indicador', this.indicadorHistorico);
                
                if (this.fechaInicio) {
                    formData.append('fecha_inicio', this.fechaInicio);
                }
                if (this.fechaFin) {
                    formData.append('fecha_fin', this.fechaFin);
                }
                
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.mostrarAlerta('success', '¡Carga Completa!', data.mensaje);
                    this.obtenerEstadisticas();
                    this.verLog();
                } else {
                    this.mostrarAlerta('danger', 'Error', data.mensaje);
                }
                
            } catch (error) {
                this.mostrarAlerta('danger', 'Error', 'Error de conexión: ' + error.message);
            } finally {
                this.procesando = false;
            }
        },
        
        async obtenerEstadisticas() {
            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_estadisticas');
                
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.estadisticas = data.data;
                }
                
            } catch (error) {
                console.error('Error obteniendo estadísticas:', error);
            }
        },
        
        async verLog() {
            try {
                const formData = new FormData();
                formData.append('accion', 'ver_log');
                
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.data.log) {
                    // Escapar HTML y preservar saltos de línea
                    this.logContent = this.escapeHtml(data.data.log);
                }
                
            } catch (error) {
                console.error('Error obteniendo log:', error);
            }
        },
        
        async limpiarLog() {
            if (!confirm('¿Estás seguro de que deseas limpiar el log?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('accion', 'limpiar_log');
                
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.logContent = 'Log limpiado.';
                    this.mostrarAlerta('info', 'Log limpiado', 'El archivo de log ha sido limpiado correctamente.');
                }
                
            } catch (error) {
                console.error('Error limpiando log:', error);
            }
        },
        
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    }
}
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
```

## Instrucciones de instalación:

### 1. **Estructura de archivos**:
```
/tu_proyecto/
├── config.php
├── Database.php
├── banxico.log (se creará automáticamente)
└── includes/
    └── banxico/
        ├── BanxicoService.php
        ├── sincronizar_diario.php
        ├── sincronizar_historicos.php
        ├── ajax_handler.php
        └── index.php