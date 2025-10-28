// public/js/auth.js
class AuthAPI {
    static baseURL = 'http://localhost/proyecto_paz/api/auth.php';
    
    static async request(endpoint, data = null) {
        const url = `${this.baseURL}?action=${endpoint}`;
        console.log(`API Request: ${url}`, data);
        
        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: data ? JSON.stringify(data) : null
        };
        
        try {
            const response = await fetch(url, options);
            
            // SOLUCIÓN: Primero obtener el texto de la respuesta
            const responseText = await response.text();
            console.log(`Response Text:`, responseText);
            console.log(`API Response Status: ${response.status}`);
            
            let result;
            try {
                // Intentar parsear como JSON
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Error parsing JSON:', parseError);
                console.error('Raw response:', responseText);
                throw new Error(`Respuesta del servidor no válida: ${responseText.substring(0, 100)}...`);
            }
            
            console.log(`API Response:`, result);
            
            if (!response.ok) {
                throw new Error(result.message || 'Error en la petición');
            }
            
            return result;
        } catch (error) {
            console.error('Error API:', error);
            throw error;
        }
    }
    
    static async signup(userData) {
        return await this.request('signup', userData);
    }
    
    static async signin(credentials) {
        return await this.request('signin', credentials);
    }
    
    static async signout() {
        return await this.request('signout');
    }
    
    static async checkAuth() {
        const response = await fetch(`${this.baseURL}?action=check`);
        return await response.json();
    }
}

// UI Manager para autenticación
class AuthUI {
    static showLogin() {
        window.location.href = 'index.php?page=login';
    }
    
    static showRegister() {
        window.location.href = 'index.php?page=register';
    }
    
    static showHome() {
        window.location.href = 'index.php?page=home';
    }
    
    static updateUI(user = null) {
        const authSection = document.getElementById('authSection');
        const userSection = document.getElementById('userSection');
        const userNameSpan = document.getElementById('userName');
        
        if (user) {
            // Usuario logueado
            if (authSection) authSection.style.display = 'none';
            if (userSection) userSection.style.display = 'block';
            if (userNameSpan) userNameSpan.textContent = user.fullname || user.username;
        } else {
            // Usuario no logueado
            if (authSection) authSection.style.display = 'block';
            if (userSection) userSection.style.display = 'none';
        }
    }
    
    static showMessage(message, type = 'success') {
        // Crear o reutilizar elemento de mensaje
        let messageDiv = document.getElementById('authMessage');
        
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'authMessage';
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                z-index: 10000;
                max-width: 300px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            `;
            document.body.appendChild(messageDiv);
        }
        
        messageDiv.style.backgroundColor = type === 'success' ? '#4CAF50' : '#f44336';
        messageDiv.textContent = message;
        messageDiv.style.display = 'block';
        
        // Ocultar después de 5 segundos
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
    
    static showLoading(button) {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Procesando...';
        return () => {
            button.disabled = false;
            button.textContent = originalText;
        };
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Formulario de registro
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const button = this.querySelector('button[type="submit"]');
            const resetLoading = AuthUI.showLoading(button);
            
            try {
                const formData = new FormData(this);
                const userData = {
                    fullname: formData.get('fullname'),
                    username: formData.get('username'),
                    email: formData.get('email'),
                    password: formData.get('password'),
                    confirmPassword: formData.get('confirmPassword')
                };
                
                // Validar contraseñas
                if (userData.password !== userData.confirmPassword) {
                    throw new Error('Las contraseñas no coinciden');
                }
                
                await AuthAPI.signup(userData);
                AuthUI.showMessage('Registro exitoso. Redirigiendo...', 'success');
                
                setTimeout(() => {
                    AuthUI.showLogin();
                }, 2000);
                
            } catch (error) {
                AuthUI.showMessage(error.message, 'error');
            } finally {
                resetLoading();
            }
        });
    }
    
    // Formulario de login
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const button = this.querySelector('button[type="submit"]');
            const resetLoading = AuthUI.showLoading(button);
            
            try {
                const formData = new FormData(this);
                const credentials = {
                    username: formData.get('username'),
                    password: formData.get('password')
                };
                
                const result = await AuthAPI.signin(credentials);
                AuthUI.showMessage(`Bienvenido/a ${result.user.fullname}`, 'success');
                
                setTimeout(() => {
                    AuthUI.showHome();
                }, 1000);
                
            } catch (error) {
                AuthUI.showMessage(error.message, 'error');
            } finally {
                resetLoading();
            }
        });
    }
    
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async function() {
            try {
                await AuthAPI.signout();
                AuthUI.showMessage('Sesión cerrada exitosamente', 'success');
                
                setTimeout(() => {
                    AuthUI.showHome();
                }, 1000);
                
            } catch (error) {
                AuthUI.showMessage('Error al cerrar sesión', 'error');
            }
        });
    }
    
    // Verificar estado de autenticación al cargar la página
    AuthAPI.checkAuth()
        .then(result => {
            if (result.success) {
                AuthUI.updateUI(result.user);
            } else {
                AuthUI.updateUI(null);
            }
        })
        .catch(() => {
            AuthUI.updateUI(null);
        });
});