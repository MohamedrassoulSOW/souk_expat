import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// Thème sombre / clair
    const toggleBtn = document.getElementById('themeToggle');
    const html = document.documentElement;

    // Charger le thème sauvegardé
    if (localStorage.getItem('theme') === 'dark') {
        html.setAttribute('data-bs-theme', 'dark');
        toggleBtn.textContent = '☀️';
    }

    toggleBtn.addEventListener('click', () => {
        if (html.getAttribute('data-bs-theme') === 'dark') {
            html.removeAttribute('data-bs-theme');
            localStorage.setItem('theme', 'light');
            toggleBtn.textContent = '🌙';
        } else {
            html.setAttribute('data-bs-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            toggleBtn.textContent = '☀️';
        }
    });

// Ajouter la fermeture automatique des alertes après 3 secondes
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            document.querySelectorAll('.auto-dismiss').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 3000); // ⏱️ 4 secondes
    });

    
    // Code pour gérer le chargement des messages dans le chat
   
    // Scroll automatique vers le bas au chargement
    const container = document.getElementById('message-container');
    container.scrollTop = container.scrollHeight;

    // Script Mercure (à adapter avec ton URL de Hub si besoin)
    const url = new URL('{{ mercure_hub_url|escape("js") }}');
    url.searchParams.append('topic', 'chat_thread_{{ thread.id }}');

    const eventSource = new EventSource(url);
    eventSource.onmessage = event => {
        container.insertAdjacentHTML('beforeend', event.data);
        container.scrollTop = container.scrollHeight;
    };


