/**
 * Dashboard.js - Funcionalidad del dashboard
 * Incluye: reloj en tiempo real, notificaciones, actualización de stats, etc.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Inicialización
    // ========================================
    
    console.log('🚀 Dashboard initialized');
    
    // ========================================
    // Reloj en tiempo real
    // ========================================
    
    function updateClock() {
        const now = new Date();
        const options = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        
        const timeString = now.toLocaleTimeString('es-MX', options);
        const clockElement = document.getElementById('currentTime');
        
        if (clockElement) {
            clockElement.textContent = timeString;
        }
    }
    
    // Actualizar reloj cada segundo
    updateClock();
    setInterval(updateClock, 1000);
    
    // ========================================
    // Animación de números (count up)
    // ========================================
    
    function animateValue(element, start, end, duration) {
        if (!element) return;
        
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            element.textContent = value.toLocaleString('es-MX');
            
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        
        window.requestAnimationFrame(step);
    }
    
    // Animar números de las tarjetas al cargar
    const statCards = document.querySelectorAll('.text-3xl');
    statCards.forEach((card, index) => {
        const finalValue = parseInt(card.textContent) || 0;
        card.textContent = '0';
        setTimeout(() => {
            animateValue(card, 0, finalValue, 1000);
        }, 100 * (index + 1));
    });
    
    // ========================================
    // Actualización automática de estadísticas
    // ========================================
    
    function refreshStats() {
        // Aquí harías una llamada AJAX para obtener stats actualizadas
        // Por ahora solo mostramos un mensaje en consola
        console.log('📊 Refreshing stats...');
        
        // Ejemplo de cómo sería:
        /*
        fetch('api/stats.php')
            .then(response => response.json())
            .then(data => {
                // Actualizar los valores en el DOM
                updateStatCard('total_users', data.total_users);
                updateStatCard('active_sessions', data.active_sessions);
                // etc...
            })
            .catch(error => {
                console.error('Error refreshing stats:', error);
            });
        */
    }
    
    // Actualizar estadísticas cada 5 minutos
    setInterval(refreshStats, 5 * 60 * 1000);
    
    // ========================================
    // Gestión de notificaciones
    // ========================================
    
    let notificationCount = 0;
    
    function checkNotifications() {
        // Aquí harías una llamada AJAX para verificar nuevas notificaciones
//        console.log('🔔 Checking for notifications...');
        
        // Ejemplo:
        /*
        fetch('api/notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.count > notificationCount) {
                    showNotificationBadge(data.count);
                    playNotificationSound();
                }
                notificationCount = data.count;
            })
            .catch(error => {
                console.error('Error checking notifications:', error);
            });
        */
    }
    
    function showNotificationBadge(count) {
        const bellIcon = document.querySelector('.fa-bell');
        if (bellIcon && count > 0) {
            // El badge ya existe en el HTML
            console.log(`📬 ${count} new notifications`);
        }
    }
    
    function playNotificationSound() {
        // Reproducir sonido de notificación (opcional)
        const audio = new Audio('/public/sounds/notification.mp3');
        audio.volume = 0.3;
        audio.play().catch(e => {
            console.log('Could not play notification sound:', e);
        });
    }
    
    // Verificar notificaciones cada 30 segundos
    setInterval(checkNotifications, 30 * 1000);
    
    // ========================================
    // Sidebar responsivo en móvil
    // ========================================
    
    function handleMobileSidebar() {
        if (window.innerWidth <= 768) {
            // En móvil, cerrar sidebar al hacer clic en un link
            const sidebarLinks = document.querySelectorAll('aside nav a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Alpine.js manejará el estado
                    // Este es solo para cerrar el sidebar
                    const event = new CustomEvent('close-sidebar');
                    window.dispatchEvent(event);
                });
            });
        }
    }
    
    handleMobileSidebar();
    window.addEventListener('resize', handleMobileSidebar);
    
    // ========================================
    // Confirmación al cerrar sesión
    // ========================================
    
    const logoutLink = document.querySelector('a[href="logout.php"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            const confirmed = confirm('¿Estás seguro de que deseas cerrar sesión?');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    }
    
    // ========================================
    // Auto-save de configuraciones (localStorage)
    // ========================================
    
    // Guardar estado del sidebar
    function saveSidebarState(isOpen) {
        localStorage.setItem('sidebarOpen', isOpen);
    }
    
    function loadSidebarState() {
        const savedState = localStorage.getItem('sidebarOpen');
        return savedState !== null ? savedState === 'true' : true;
    }
    
    // Aplicar estado guardado al cargar
    const initialSidebarState = loadSidebarState();
    if (window.Alpine) {
        // Si Alpine está disponible, setear el estado inicial
        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboard', () => ({
                sidebarOpen: initialSidebarState
            }));
        });
    }
    
    // ========================================
    // Tooltips personalizados
    // ========================================
    
    function initTooltips() {
        const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
        
        elementsWithTooltip.forEach(element => {
            element.addEventListener('mouseenter', function() {
                // El CSS ya maneja la visualización
                // Aquí podrías agregar lógica adicional si es necesario
            });
        });
    }
    
    initTooltips();
    
    // ========================================
    // Manejo de errores globales
    // ========================================
    
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        // Aquí podrías enviar el error a un servicio de logging
    });
    
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled promise rejection:', e.reason);
    });
    
    // ========================================
    // Session timeout warning
    // ========================================
    
    let sessionTimeout;
    let warningTimeout;
    const SESSION_DURATION = 3600000; // 1 hora en milisegundos
    const WARNING_BEFORE = 300000; // Advertir 5 minutos antes
    
    function resetSessionTimer() {
        clearTimeout(sessionTimeout);
        clearTimeout(warningTimeout);
        
        // Advertencia antes de expirar
        warningTimeout = setTimeout(() => {
            showSessionWarning();
        }, SESSION_DURATION - WARNING_BEFORE);
        
        // Timeout de sesión
        sessionTimeout = setTimeout(() => {
            handleSessionExpired();
        }, SESSION_DURATION);
    }
    
    function showSessionWarning() {
        if (confirm('Tu sesión está por expirar. ¿Deseas continuar?')) {
            // Renovar sesión
            fetch('api/refresh-session.php')
                .then(() => {
                    resetSessionTimer();
                    console.log('✅ Session refreshed');
                })
                .catch(error => {
                    console.error('Error refreshing session:', error);
                });
        }
    }
    
    function handleSessionExpired() {
        alert('Tu sesión ha expirado. Serás redirigido al login.');
        window.location.href = 'logout.php';
    }
    
    // Resetear timer en actividad del usuario
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetSessionTimer, { passive: true });
    });
    
    // Iniciar timer
    resetSessionTimer();
    
    // ========================================
    // Búsqueda rápida (preparado para futuro)
    // ========================================
    
    // Atajo de teclado: Ctrl+K o Cmd+K para búsqueda rápida
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openQuickSearch();
        }
    });
    
    function openQuickSearch() {
        console.log('🔍 Quick search opened (not implemented yet)');
        // Aquí implementarías un modal de búsqueda rápida
    }
    
    // ========================================
    // Gráficos y visualizaciones (preparado)
    // ========================================
    
    function initCharts() {
        // Aquí inicializarías librerías como Chart.js si las usas
        console.log('📈 Charts ready to be initialized');
    }
    
    initCharts();
    
    // ========================================
    // Export data functions
    // ========================================
    
    window.exportToCSV = function(data, filename) {
        const csv = convertToCSV(data);
        downloadFile(csv, filename, 'text/csv');
    };
    
    window.exportToPDF = function() {
        window.print();
    };
    
    function convertToCSV(data) {
        // Implementación básica de conversión a CSV
        const headers = Object.keys(data[0]);
        const rows = data.map(row => 
            headers.map(header => 
                JSON.stringify(row[header] || '')
            ).join(',')
        );
        
        return [headers.join(','), ...rows].join('\n');
    }
    
    function downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        window.URL.revokeObjectURL(url);
    }
    
    // ========================================
    // Debug helpers (solo en desarrollo)
    // ========================================
    
    const isDevelopment = window.location.hostname === 'localhost' || 
                          window.location.hostname === '127.0.0.1';
    
    if (isDevelopment) {
        window.dashboardDebug = {
            refreshStats: refreshStats,
            checkNotifications: checkNotifications,
            animateNumbers: () => {
                statCards.forEach((card, index) => {
                    const finalValue = parseInt(card.textContent) || 0;
                    animateValue(card, 0, finalValue, 1000);
                });
            },
            resetSession: resetSessionTimer
        };
        
//        console.log('💡 Debug helpers available:', Object.keys(window.dashboardDebug));
    }
    
    // ========================================
    // Service Worker (PWA preparado)
    // ========================================
    
    if ('serviceWorker' in navigator) {
        // Descomentar cuando tengas un service worker
        /*
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('✅ Service Worker registered'))
            .catch(err => console.error('❌ Service Worker registration failed:', err));
        */
    }
    
//    console.log('✅ Dashboard fully loaded');
    
});

// ========================================
// Funciones globales útiles
// ========================================

// Formatear números
function formatNumber(num) {
    return new Intl.NumberFormat('es-MX').format(num);
}

// Formatear moneda
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
}

// Formatear fechas
function formatDate(date) {
    return new Intl.DateTimeFormat('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
}

// Copiar al portapapeles
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        console.log('📋 Copied to clipboard');
        return true;
    } catch (err) {
        console.error('Failed to copy:', err);
        return false;
    }
}