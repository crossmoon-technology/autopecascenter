const COOKIE_CONSENT_STORAGE_KEY = 'autopecascenter_cookie_consent';

function initCookieConsent() {
    const modal = document.querySelector('[data-cookie-consent]');

    if (!modal) {
        return;
    }

    if (localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY) === 'accepted') {
        return;
    }

    modal.classList.add('is-visible');
    document.body.style.overflow = 'hidden';

    modal.querySelector('[data-cookie-consent-accept]')?.addEventListener('click', () => {
        localStorage.setItem(COOKIE_CONSENT_STORAGE_KEY, 'accepted');
        modal.classList.remove('is-visible');
        document.body.style.overflow = '';
    });
}

document.addEventListener('DOMContentLoaded', initCookieConsent);
