/**
 * Site-wide progressive enhancement: mobile nav toggle, the native
 * <dialog>-based Suggest an Edit modal, and the Web Share API button.
 * Vanilla JS only — no framework, no build step.
 */
(function () {
    'use strict';

    function initMenuToggle() {
        var toggle = document.querySelector('.site-header__menu-toggle');
        var nav = document.getElementById('primary-menu');

        if (!toggle || !nav) {
            return;
        }

        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function initSuggestEditDialogs() {
        document.querySelectorAll('[data-dialog-target]').forEach(function (button) {
            var dialog = document.getElementById(button.getAttribute('data-dialog-target'));

            if (!dialog) {
                return;
            }

            button.addEventListener('click', function () {
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', 'open');
                }
            });
        });
    }

    /**
     * Shows the "Sign Up and Join Us" popup once per browser session
     * for logged-out visitors (the markup itself only exists in the
     * page at all when logged out — see templates/parts/signup-popup.php).
     * sessionStorage means it reappears on a visitor's next visit
     * (new tab/browser session) without nagging on every page view
     * within the same visit.
     */
    function initSignupPopup() {
        var dialog = document.getElementById('signup-popup');

        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }

        var STORAGE_KEY = 'countryWeekSignupPopupSeen';

        try {
            if (sessionStorage.getItem(STORAGE_KEY)) {
                return;
            }

            sessionStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            // Private browsing / storage disabled — just show it once
            // per page load rather than failing silently forever.
        }

        window.setTimeout(function () {
            dialog.showModal();
        }, 600);
    }

    function initNativeShare() {
        if (!navigator.share) {
            return;
        }

        document.querySelectorAll('.country-actions__share-native').forEach(function (button) {
            var wrapper = button.closest('.country-actions');

            if (!wrapper) {
                return;
            }

            button.hidden = false;

            button.addEventListener('click', function () {
                navigator.share({
                    title: wrapper.getAttribute('data-share-title') || document.title,
                    url: wrapper.getAttribute('data-share-url') || window.location.href,
                }).catch(function () {
                    // User cancelled the share sheet — nothing to do.
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initMenuToggle();
        initSuggestEditDialogs();
        initSignupPopup();
        initNativeShare();
    });
})();
