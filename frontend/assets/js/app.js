document.addEventListener('DOMContentLoaded', () => {
    console.info('Prévia visual do front-end ELOS inicializada.');

    const passwordToggle = document.querySelector('.password-toggle');
    const passwordField = document.querySelector('#senha');

    if (passwordToggle && passwordField) {
        passwordToggle.addEventListener('click', () => {
            const shouldShow = passwordField.type === 'password';
            passwordField.type = shouldShow ? 'text' : 'password';
            passwordToggle.textContent = shouldShow ? 'Ocultar' : 'Mostrar';
            passwordToggle.setAttribute('aria-label', shouldShow ? 'Ocultar senha' : 'Mostrar senha');
        });
    }
});
