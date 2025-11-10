/**
 * Login.js - Funcionalidad del formulario de login
 * Incluye: validación, mostrar/ocultar contraseña, efectos visuales
 */

document.addEventListener('DOMContentLoaded', function() {    
    // Elementos del DOM
    const loginForm = document.getElementById('loginForm');
    const identifierInput = document.getElementById('identifier');
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const eyeIcon = document.getElementById('eyeIcon');
    const submitButton = loginForm.querySelector('button[type="submit"]');
    
    // ========================================
    // Mostrar/Ocultar Contraseña
    // ========================================
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Cambiar icono
            if (type === 'text') {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
    }
    
    // ========================================
    // Validación en tiempo real
    // ========================================
    
    // Validar email/usuario
    identifierInput.addEventListener('blur', function() {
        validateIdentifier();
    });
    
    identifierInput.addEventListener('input', function() {
        clearError(identifierInput);
    });
    
    // Validar contraseña
    passwordInput.addEventListener('blur', function() {
        validatePassword();
    });
    
    passwordInput.addEventListener('input', function() {
        clearError(passwordInput);
    });
    
    // ========================================
    // Validación del formulario al enviar
    // ========================================
    loginForm.addEventListener('submit', function(e) {
        
        // Validar campos
        const isIdentifierValid = validateIdentifier();
        const isPasswordValid = validatePassword();
        
        if (!isIdentifierValid || !isPasswordValid) {
            e.preventDefault();
            shakeForm();
            return false;
        }
        
        // Mostrar estado de carga
        showLoading();
    });
    
    // ========================================
    // Funciones de Validación
    // ========================================
    
    function validateIdentifier() {
        const value = identifierInput.value.trim();
        
        if (value === '') {
            showError(identifierInput, 'El email o usuario es requerido');
            return false;
        }
        
        if (value.length < 3) {
            showError(identifierInput, 'Mínimo 3 caracteres');
            return false;
        }
        
        clearError(identifierInput);
        return true;
    }
    
    function validatePassword() {
        const value = passwordInput.value;
        
        if (value === '') {
            showError(passwordInput, 'La contraseña es requerida');
            return false;
        }
        
        if (value.length < 6) {
            showError(passwordInput, 'Mínimo 6 caracteres');
            return false;
        }
        
        clearError(passwordInput);
        return true;
    }
    
    // ========================================
    // Funciones de UI
    // ========================================
    
    function showError(input, message) {
        const parent = input.parentElement;
        
        // Remover error existente
        const existingError = parent.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Agregar clase de error
        input.classList.add('border-red-500');
        input.classList.remove('border-gray-300');
        
        // Crear mensaje de error
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message text-red-500 text-sm mt-1 flex items-center';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle mr-1"></i>
            <span>${message}</span>
        `;
        
        parent.appendChild(errorDiv);
    }
    
    function clearError(input) {
        const parent = input.parentElement;
        
        // Remover mensaje de error
        const existingError = parent.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Remover clase de error
        input.classList.remove('border-red-500');
        input.classList.add('border-gray-300');
    }
    
    function shakeForm() {
        loginForm.classList.add('shake');
        
        setTimeout(() => {
            loginForm.classList.remove('shake');
        }, 500);
    }
    
    function showLoading() {
        // Deshabilitar el botón
        submitButton.disabled = true;
        submitButton.classList.add('loading');
        
        // Cambiar texto
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Ingresando...';
        
        // Si hay error en el servidor, revertir después de 5 segundos
        setTimeout(() => {
            if (submitButton.disabled) {
                submitButton.disabled = false;
                submitButton.classList.remove('loading');
                submitButton.innerHTML = originalText;
            }
        }, 5000);
    }
    
    // ========================================
    // Recordar último usuario (opcional)
    // ========================================
    
    // Si hay un usuario guardado en localStorage, autocompletar
    const rememberedUser = localStorage.getItem('remembered_user');
    if (rememberedUser && identifierInput.value === '') {
        identifierInput.value = rememberedUser;
    }
    
    // Guardar usuario si marca "Recordarme"
    loginForm.addEventListener('submit', function() {
        const rememberMe = document.getElementById('remember_me').checked;
        
        if (rememberMe) {
            localStorage.setItem('remembered_user', identifierInput.value.trim());
        } else {
            localStorage.removeItem('remembered_user');
        }
    });
    
    // ========================================
    // Autocompletado inteligente
    // ========================================
    
    // Si el usuario escribe un @, probablemente es email
    identifierInput.addEventListener('input', function(e) {
        const value = e.target.value;
        
        if (value.includes('@') && !value.includes('.')) {
            // Sugerir dominios comunes
            const commonDomains = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'];
            // Esta funcionalidad se puede expandir con un datalist si se desea
        }
    });
    
    // ========================================
    // Detección de CAPS LOCK
    // ========================================
    
    passwordInput.addEventListener('keyup', function(e) {
        const capsLockOn = e.getModifierState && e.getModifierState('CapsLock');
        
        const parent = passwordInput.parentElement;
        let capsWarning = parent.querySelector('.caps-warning');
        
        if (capsLockOn) {
            if (!capsWarning) {
                capsWarning = document.createElement('div');
                capsWarning.className = 'caps-warning text-yellow-600 text-sm mt-1 flex items-center';
                capsWarning.innerHTML = `
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span>Mayúsculas activadas</span>
                `;
                parent.appendChild(capsWarning);
            }
        } else {
            if (capsWarning) {
                capsWarning.remove();
            }
        }
    });
    
    // ========================================
    // Atajos de teclado
    // ========================================
    
    // Enter en identifier pasa a password
    identifierInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            passwordInput.focus();
        }
    });
    
    // ========================================
    // Prevenir ataques de timing
    // ========================================
    
    // Agregar un pequeño delay aleatorio en la validación
    // para prevenir ataques de timing que puedan determinar usuarios válidos
    let validationTimeout;
    
    identifierInput.addEventListener('input', function() {
        clearTimeout(validationTimeout);
        validationTimeout = setTimeout(() => {
            // Validación con delay
        }, Math.random() * 100 + 50);
    });
    
    // ========================================
    // Animación de entrada de mensajes de error
    // ========================================
    
    const errorAlerts = document.querySelectorAll('.bg-red-50, .bg-green-50');
    errorAlerts.forEach(alert => {
        // Ya tienen animación por CSS, pero podemos agregar funcionalidad de cerrar
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.className = 'ml-auto text-gray-400 hover:text-gray-600 transition';
        closeBtn.onclick = function() {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        };
        
        alert.querySelector('div').appendChild(closeBtn);
    });
    
    // ========================================
    // Protección contra bots simples
    // ========================================
    
    // Agregar un campo honeypot invisible
    const honeypot = document.createElement('input');
    honeypot.type = 'text';
    honeypot.name = 'website'; // Campo que humanos no deberían llenar
    honeypot.style.position = 'absolute';
    honeypot.style.left = '-9999px';
    honeypot.tabIndex = -1;
    loginForm.appendChild(honeypot);
    
    // Si el honeypot tiene valor, probablemente es un bot
    loginForm.addEventListener('submit', function(e) {
        if (honeypot.value !== '') {
            e.preventDefault();
            console.warn('Possible bot detected');
            return false;
        }
    });
    
    // ========================================
    // Analytics y tracking (opcional)
    // ========================================
    
    // Registrar intentos de login para analytics
    loginForm.addEventListener('submit', function() {
        // Aquí puedes agregar código para enviar eventos a Google Analytics, etc.
        // Ejemplo:
        // gtag('event', 'login_attempt', { method: 'email' });
    });
    
    // ========================================
    // Accesibilidad mejorada
    // ========================================
    
    // Anunciar errores a lectores de pantalla
    function announceError(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('role', 'alert');
        announcement.setAttribute('aria-live', 'assertive');
        announcement.className = 'sr-only'; // Solo para lectores de pantalla
        announcement.textContent = message;
        document.body.appendChild(announcement);
        
        setTimeout(() => {
            announcement.remove();
        }, 1000);
    }
    
    // ========================================
    // Debug mode (solo desarrollo)
    // ========================================
    
    // Detectar si estamos en desarrollo
    const isDevelopment = window.location.hostname === 'localhost' || 
                          window.location.hostname === '127.0.0.1';
    
    if (isDevelopment) {       
        // Agregar helper de desarrollo
        window.loginDebug = {
            fillTestUser: () => {
                identifierInput.value = 'admin@test.com';
                passwordInput.value = 'test1234';
                console.log('✅ Test credentials filled');
            },
            clearRemembered: () => {
                localStorage.removeItem('remembered_user');
                console.log('🗑️ Remembered user cleared');
            }
        };
        
    }
    
});

// ========================================
// Funciones globales útiles
// ========================================

// Función para validar formato de email
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Función para verificar fortaleza de contraseña (para registro)
function getPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    return strength;
}