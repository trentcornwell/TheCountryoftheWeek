/**
 * Site-wide progressive enhancement: mobile nav toggle, the native
 * <dialog>-based Suggest an Edit modal, and the single Share button
 * (Web Share API, falling back to copy-to-clipboard). Vanilla JS
 * only — no framework, no build step.
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

    /**
     * Progressive enhancement only: pre-selects the visitor's own
     * detected time zone in the registration/preferences form's
     * dropdown, if the browser supports detection and that exact zone
     * is one of the dropdown's options. Both forms work perfectly well
     * with JS disabled — the dropdown just keeps its server-rendered
     * default selection (the site's own time zone) in that case.
     */
    function initTimezoneAutodetect() {
        var select = document.getElementById('cw_reg_timezone') || document.getElementById('cw_timezone');

        if (!select || typeof Intl === 'undefined' || typeof Intl.DateTimeFormat !== 'function') {
            return;
        }

        try {
            var detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
            var option = detected && select.querySelector('option[value="' + detected + '"]');

            if (option) {
                select.value = detected;
            }
        } catch (e) {
            // Detection unsupported/blocked — leave the default selection.
        }
    }

    /**
     * The one "Share" button (see templates/parts/share-buttons.php):
     * the native OS share sheet where available, otherwise copying the
     * link to the clipboard — so every visitor has a working share
     * action from a single button, not just devices with
     * navigator.share. Both branches degrade gracefully to "do
     * nothing" on very old/locked-down browsers with neither API,
     * rather than throwing.
     */
    function initShareButton() {
        document.querySelectorAll('.country-actions__share-native').forEach(function (button) {
            var wrapper = button.closest('.country-actions');

            if (!wrapper) {
                return;
            }

            var defaultLabel = button.textContent;
            var copiedLabel = button.getAttribute('data-copied-label') || defaultLabel;

            button.addEventListener('click', function () {
                var shareUrl = wrapper.getAttribute('data-share-url') || window.location.href;

                if (navigator.share) {
                    navigator.share({
                        title: wrapper.getAttribute('data-share-title') || document.title,
                        url: shareUrl,
                    }).catch(function () {
                        // User cancelled the share sheet — nothing to do.
                    });

                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(shareUrl).then(function () {
                        button.textContent = copiedLabel;

                        window.setTimeout(function () {
                            button.textContent = defaultLabel;
                        }, 2000);
                    }).catch(function () {
                        // Clipboard write blocked (permissions/insecure context) — nothing more to do.
                    });
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initMenuToggle();
        initSuggestEditDialogs();
        initSignupPopup();
        initShareButton();
        initTimezoneAutodetect();
    });
})();
