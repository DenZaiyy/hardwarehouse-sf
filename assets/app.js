import './stimulus_bootstrap.js';
import './styles/app.css';

const button = document.getElementById('goTop');

window.addEventListener('scroll', () => {
    if (window.scrollY > 100) {
        button.classList.remove('opacity-0', 'invisible', 'translate-y-4');
        button.classList.add('opacity-100', 'visible', 'translate-y-0');
    } else {
        button.classList.add('opacity-0', 'invisible', 'translate-y-4');
        button.classList.remove('opacity-100', 'visible', 'translate-y-0');
    }
});

button.addEventListener('click', () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});
