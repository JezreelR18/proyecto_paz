if (!window.__AUTH_INIT__) {
  window.__AUTH_INIT__ = true;
    document.addEventListener("DOMContentLoaded", () => {
    const authBtn = document.getElementById("authButton");
    const toolsItem = document.getElementById("toolsItem");
    const libraryItem = document.getElementById("libraryItem");
    const questionnaireItem = document.getElementById("questionnaireItem");

    const regForm = document.getElementById('registerForm');
    if (regForm) {
        regForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fullname = document.getElementById('fullname').value.trim();
        const username = document.getElementById('username').value.trim();
        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirm  = document.getElementById('confirmPassword').value;

        if (password !== confirm) {
            alert('Las contraseñas no coinciden');
            return;
        }

        try {
            const res = await fetch('api/auth.php?action=signup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fullname, username, email, password })
            });

            const text = await res.text();
            let data; try { data = JSON.parse(text); } catch (_) {}

            if (!res.ok) {
            console.error('Signup error:', res.status, text);
            alert((data && (data.message || data.error)) || `Error ${res.status} al registrar`);
            return;
            }

            if (data && data.success) {
            alert('Registro exitoso. Ahora inicia sesión.');
            window.location.href = 'index.php?page=login';
            } else {
            alert((data && data.message) || 'No se pudo registrar');
            }
        } catch (err) {
            console.error(err);
            alert('Error de red al registrar');
        }
        });
    }

    checkSession();

    async function checkSession() {
        try {
        const res = await fetch("api/auth.php?action=session");
        const data = await res.json();

        if (data.data.loggedIn) {
            setLoggedInState(data.user);
        } else {
            setLoggedOutState();
        }
        } catch (err) {
        console.error("Error comprobando sesión:", err);
        setLoggedOutState();
        }
    }

    function setLoggedInState(user) {
        authBtn.textContent = "Cerrar sesión";
        authBtn.onclick = async () => {
        await logout();
        };
        toolsItem.style.display = "block";
        libraryItem.style.display = "block";
        questionnaireItem.style.display = "block";
    }

    function setLoggedOutState() {
        authBtn.textContent = "Acceder";
        authBtn.onclick = () => {
        window.location.href = "index.php?page=login";
        };
        toolsItem.style.display = "none";
        libraryItem.style.display = "none";
        questionnaireItem.style.display = "none";
    }

    async function logout() {
        try {
        const res = await fetch("api/auth.php?action=signout", {
            method: "POST",
        });
        const data = await res.json();
        if (data.success) {
            setLoggedOutState();
            window.location.href = "index.php?page=home";
        }
        } catch (err) {
        console.error("Error cerrando sesión:", err);
        }
    }

    const loginForm = document.getElementById("loginForm");
    if (loginForm) {
        loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const payload = {
            username: document.getElementById("username").value.trim(),
            password: document.getElementById("password").value.trim(),
        };

        try {
            const res = await fetch("api/auth.php?action=signin", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
            });
            console.log('RESPUESTA: ', res);
            const data = await res.json();
            if (data.success) {
            window.location.href = "index.php?page=home";
            } else {
            alert(data.message || "Credenciales inválidas");
            }
        } catch (err) {
            console.error("Error en login:", err);
            alert("Error de conexión con el servidor");
        }
        });
    }
    });
}