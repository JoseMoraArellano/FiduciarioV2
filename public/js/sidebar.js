/**
 * Sidebar.js - Componente Alpine.js para el sidebar
 * Maneja: toggle, estado persistente, responsividad
 */

/**
 * Componente Alpine.js del Sidebar
 */
function sidebar(initialState = true, userId = null) {
    return {
        sidebarOpen: initialState,
        userId: userId,
        isMobile: false,
        isLoading: false,
        
        /**
         * Inicialización del componente
         */
        init() {
            // Detectar si es móvil
            this.checkMobile();
            
            // Listener para cambios de tamaño de ventana
            window.addEventListener('resize', () => {
                this.checkMobile();
            });
            
            // En móvil, cerrar sidebar por defecto
            if (this.isMobile && this.sidebarOpen) {
                this.sidebarOpen = false;
            }
/*            
            // Log para debug
            if (this.isDevelopment()) {
                console.log('🎨 Sidebar initialized', {
                    open: this.sidebarOpen,
                    userId: this.userId,
                    isMobile: this.isMobile
                });
            }
*/                
        },
        
        /**
         * Toggle del sidebar
         */
        async toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            
            // Guardar estado en BD
            await this.saveSidebarState();
            
            // En móvil, manejar overlay
            if (this.isMobile) {
                this.handleMobileOverlay();
            }
            
            // Emitir evento personalizado
            window.dispatchEvent(new CustomEvent('sidebar-toggled', {
                detail: { isOpen: this.sidebarOpen }
            }));
        },
        
        /**
         * Abrir sidebar
         */
        openSidebar() {
            if (!this.sidebarOpen) {
                this.toggleSidebar();
            }
        },
        
        /**
         * Cerrar sidebar
         */
        closeSidebar() {
            if (this.sidebarOpen) {
                this.toggleSidebar();
            }
        },
        
        /**
         * Guardar estado en la base de datos vía API
         */
        async saveSidebarState() {
            if (!this.userId) {
                console.warn('No user ID provided, cannot save sidebar state');
                return;
            }
            
            this.isLoading = true;
            
            try {
                const response = await fetch('/API/save-sidebar-state.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        is_open: this.sidebarOpen
                    }),
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    if (this.isDevelopment()) {
                        console.log('✅ Sidebar state saved:', data);
                    }
                } else {
                    console.error('❌ Failed to save sidebar state:', data.message);
                }
                
            } catch (error) {
                console.error('❌ Error saving sidebar state:', error);
                
                // Fallback: guardar en localStorage
                this.saveSidebarStateLocal();
                
            } finally {
                this.isLoading = false;
            }
        },
        
        /**
         * Guardar estado en localStorage (fallback)
         */
        saveSidebarStateLocal() {
            try {
                localStorage.setItem('sidebar_open', this.sidebarOpen ? '1' : '0');
                localStorage.setItem('sidebar_open_timestamp', Date.now().toString());
                
                if (this.isDevelopment()) {
                    console.log('💾 Sidebar state saved to localStorage (fallback)');
                }
            } catch (e) {
                console.error('Error saving to localStorage:', e);
            }
        },
        
        /**
         * Cargar estado desde localStorage
         */
        loadSidebarStateLocal() {
            try {
                const saved = localStorage.getItem('sidebar_open');
                if (saved !== null) {
                    this.sidebarOpen = saved === '1';
                    return true;
                }
            } catch (e) {
                console.error('Error loading from localStorage:', e);
            }
            return false;
        },
        
        /**
         * Detectar si es dispositivo móvil
         */
        checkMobile() {
            this.isMobile = window.innerWidth < 768;
            
            // En móvil, cerrar sidebar automáticamente
            if (this.isMobile && this.sidebarOpen) {
                this.sidebarOpen = false;
            }
        },
        
        /**
         * Manejar overlay en móvil
         */
        handleMobileOverlay() {
            if (!this.isMobile) return;
            
            const overlay = document.getElementById('sidebar-overlay');
            
            if (this.sidebarOpen) {
                // Crear overlay si no existe
                if (!overlay) {
                    const newOverlay = document.createElement('div');
                    newOverlay.id = 'sidebar-overlay';
                    newOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden';
                    newOverlay.addEventListener('click', () => {
                        this.closeSidebar();
                    });
                    document.body.appendChild(newOverlay);
                    
                    // Animación de entrada
                    setTimeout(() => {
                        newOverlay.style.opacity = '1';
                    }, 10);
                }
            } else {
                // Remover overlay
                if (overlay) {
                    overlay.style.opacity = '0';
                    setTimeout(() => {
                        overlay.remove();
                    }, 300);
                }
            }
        },
        
        /**
         * Cerrar sidebar al hacer clic en un link (solo móvil)
         */
        handleLinkClick(event) {
            if (this.isMobile && this.sidebarOpen) {
                // Cerrar con un pequeño delay para que se vea la transición
                setTimeout(() => {
                    this.closeSidebar();
                }, 100);
            }
        },
        
        /**
         * Manejar teclas de acceso rápido
         */
        handleKeyboard(event) {
            // Ctrl/Cmd + B para toggle sidebar
            if ((event.ctrlKey || event.metaKey) && event.key === 'b') {
                event.preventDefault();
                this.toggleSidebar();
            }
            
            // ESC para cerrar sidebar en móvil
            if (event.key === 'Escape' && this.isMobile && this.sidebarOpen) {
                this.closeSidebar();
            }
        },
        
        /**
         * Detectar modo desarrollo
         */
        isDevelopment() {
            return window.location.hostname === 'localhost' || 
                   window.location.hostname === '127.0.0.1';
        }
    };
}

// ========================================
// Event Listeners Globales
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Listener para cerrar sidebar con ESC o Ctrl+B
    document.addEventListener('keydown', function(e) {
        // Buscar instancia de Alpine
        const sidebarElement = document.querySelector('[x-data*="sidebar"]');
        if (sidebarElement && sidebarElement.__x) {
            const component = sidebarElement.__x.$data;
            if (component && typeof component.handleKeyboard === 'function') {
                component.handleKeyboard(e);
            }
        }
    });
    
    // Cerrar sidebar en móvil al hacer clic en links
    document.addEventListener('click', function(e) {
        const link = e.target.closest('aside nav a');
        if (link) {
            const sidebarElement = document.querySelector('[x-data*="sidebar"]');
            if (sidebarElement && sidebarElement.__x) {
                const component = sidebarElement.__x.$data;
                if (component && typeof component.handleLinkClick === 'function') {
                    component.handleLinkClick(e);
                }
            }
        }
    });
    
    // Listener para evento personalizado de cerrar sidebar
    window.addEventListener('close-sidebar', function() {
        const sidebarElement = document.querySelector('[x-data*="sidebar"]');
        if (sidebarElement && sidebarElement.__x) {
            const component = sidebarElement.__x.$data;
            if (component && typeof component.closeSidebar === 'function') {
                component.closeSidebar();
            }
        }
    });
    
    console.log('🎨 Sidebar scripts loaded');
});

// ========================================
// Utilidades globales
// ========================================

/**
 * API Helper para obtener estado del sidebar
 */
async function getSidebarState() {
    try {
        const response = await fetch('/API/get-sidebar-state.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        }
        
        return null;
        
    } catch (error) {
        console.error('Error getting sidebar state:', error);
        return null;
    }
}

/**
 * Exponer funciones globales para debugging
 */
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    window.sidebarDebug = {
        getState: getSidebarState,
        toggle: () => {
            const event = new CustomEvent('close-sidebar');
            window.dispatchEvent(event);
        }
    };
    
    console.log('💡 Sidebar debug tools available: window.sidebarDebug');
}