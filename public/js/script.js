function showLogin() {
    window.location.href = 'index.php?page=login';
}

function hideLogin() {
    window.location.href = 'index.php?page=home';
}

// Función para simular el login
function login() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    if (username && password) {
        alert('¡Bienvenido/a ' + username + '!');
        hideLogin();
    } else {
        alert('Por favor, ingresa usuario y contraseña');
    }
}

// Lista de palabras prohibidas CORREGIDA (sin coma extra)
const badWords = [
    'gilipollas', 'imbécil', 'analfabeto', 'retrasado', 'anormal', 'pendejo', 'pendeja',
    'puta', 'puto', 'mierda', 'carajo', 'coño', 'verga',
    'cabrón', 'cabrona', 'chinga', 'chingar', 'pinche', 'culero', 'culera',
    'joder', 'hostia', 'estúpido', 'idiota', 
    'malparido', 'hijueputa', 'marica', 'maricón', 'naco', 'pendejada',
    'maldito', 'maldita', 'diablo', 'demonio', 'carajo', 'cojones',
    'culo', 'gay', 'joto', 'perra', 'perro', 'zorra', 'mames', 'mamador'
];

function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (message) {
        // Validar si el mensaje contiene palabras prohibidas
        if (containsBadWords(message)) {
            console.log('si contiene malas palabras')
            showWarning("Por favor, mantén un lenguaje respetuoso en este espacio.");
            return; // Detener la ejecución
        }
        console.log('No contiene malas palabras')
        
        const chatMessages = document.getElementById('chatMessages');
        
        // Crear elemento de mensaje del usuario
        const userMessage = document.createElement('div');
        userMessage.className = 'message user-message';
        userMessage.innerHTML = `<p>${message}</p>`;
        
        chatMessages.appendChild(userMessage);
        
        // Limpiar el campo de entrada
        messageInput.value = '';
        
        // Simular respuesta automática después de un breve retraso
        setTimeout(() => {
            const responses = [
                "Gracias por compartir tu perspectiva. ¿Alguien más quiere añadir algo?",
                "Esa es una idea interesante. ¿Cómo podríamos aplicarla en nuestra convivencia diaria?",
                "Me gusta tu enfoque positivo. Recordemos que todos tenemos días difíciles.",
                "¿Alguien ha tenido una experiencia similar y quiere compartir cómo la manejó?"
            ];
            
            const randomResponse = responses[Math.floor(Math.random() * responses.length)];
            
            const botMessage = document.createElement('div');
            botMessage.className = 'message bot-message';
            botMessage.innerHTML = `<p>${randomResponse}</p>`;
            
            chatMessages.appendChild(botMessage);
            
            // Desplazar hacia abajo para mostrar el último mensaje
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }, 1000);
        
        // Desplazar hacia abajo para mostrar el último mensaje
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

// Función para detectar palabras prohibidas MEJORADA
function containsBadWords(text) {
    if (!text || text.trim() === '') return false;
    
    const lowerText = text.toLowerCase();
    
    // Eliminar acentos y caracteres especiales para mejor detección
    const cleanText = lowerText.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    
    // Verificar cada palabra prohibida
    for (let word of badWords) {
        // Validar que la palabra no esté vacía
        if (!word || word.trim() === '') continue;
        
        // Buscar la palabra como substring
        if (cleanText.includes(word)) {
            return true;
        }
        
        // También buscar con posibles sustituciones de caracteres
        const patterns = createPatterns(word);
        for (let pattern of patterns) {
            if (pattern && cleanText.match(pattern)) {
                return true;
            }
        }
    }
    
    return false;
}

// Crear patrones para evadir sustituciones comunes MEJORADA
function createPatterns(word) {
    // Validar que la palabra no esté vacía
    if (!word || word.trim() === '') {
        return [];
    }
    
    const substitutions = {
        'a': '[a@4áàâä]',
        'e': '[e3éèêë]',
        'i': '[i1íìîï]',
        'o': '[o0óòôö]',
        'u': '[uúùûü]',
        's': '[s5$]',
        'l': '[l1]',
        'z': '[z2]'
    };
    
    let pattern = '';
    for (let char of word) {
        pattern += substitutions[char] || char;
    }
    
    return [
        new RegExp(`\\b${pattern}\\b`, 'i'), // Palabra completa
        new RegExp(pattern, 'i') // Como substring
    ];
}

// Función para mostrar advertencia
function showWarning(message) {
    // Crear o reutilizar elemento de advertencia
    let warning = document.getElementById('chatWarning');
    
    if (!warning) {
        warning = document.createElement('div');
        warning.id = 'chatWarning';
        warning.style.cssText = `
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ef5350;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        `;
        
        const chatContainer = document.querySelector('.chat-container');
        if (chatContainer) {
            chatContainer.insertBefore(warning, chatContainer.firstChild);
        }
    }
    
    warning.textContent = message;
    warning.style.display = 'block';
    
    // Ocultar la advertencia después de 5 segundos
    setTimeout(() => {
        if (warning) {
            warning.style.display = 'none';
        }
    }, 5000);
}

// Función para cambiar la frase inspiradora
function changeQuote() {
    const quotes = [
        {
            text: "La paz comienza con una sonrisa.",
            author: "Madre Teresa de Calcuta"
        },
        {
            text: "La empatía es ver con los ojos del otro, escuchar con los oídos del otro y sentir con el corazón del otro.",
            author: "Alfred Adler"
        },
        {
            text: "El respeto al derecho ajeno es la paz.",
            author: "Benito Juárez"
        },
        {
            text: "No hay camino para la paz, la paz es el camino.",
            author: "Mahatma Gandhi"
        },
        {
            text: "La comunicación asertiva es el puente entre el respeto por uno mismo y el respeto por los demás.",
            author: "Anónimo"
        }
    ];
    
    const randomIndex = Math.floor(Math.random() * quotes.length);
    const quote = quotes[randomIndex];
    
    const quoteElement = document.querySelector('.quote');
    const authorElement = document.querySelector('.author');
    
    if (quoteElement && authorElement) {
        quoteElement.textContent = `"${quote.text}"`;
        authorElement.textContent = `- ${quote.author}`;
    }
}

// Inicializar con una frase al cargar la página
window.onload = function() {
    changeQuote();
    
    // AGREGAR EVENT LISTENER SOLO CUANDO EL ELEMENTO EXISTA
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function(e) {
            const message = e.target.value;
            
            if (containsBadWords(message)) {
                e.target.style.borderColor = '#f44336';
                e.target.style.backgroundColor = '#ffebee';
            } else {
                e.target.style.borderColor = '';
                e.target.style.backgroundColor = '';
            }
        });
        
        // También agregar evento para Enter key
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }
};

// Permitir enviar mensajes con la tecla Enter (backup)
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const messageInput = document.getElementById('messageInput');
        if (document.activeElement === messageInput) {
            sendMessage();
        }
    }
});