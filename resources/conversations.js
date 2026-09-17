/* SPDX-License-Identifier: AGPL-3.0-only */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const firstUnread = document.getElementById('first-unread-message');
        if (firstUnread && !window.location.hash) {
            firstUnread.scrollIntoView({block: 'center'});
        }

        document.querySelectorAll('.conversation-composer').forEach(function (composer) {
            composer.addEventListener('submit', function () {
                const button = composer.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.insertAdjacentHTML('beforebegin', '<span class="conversation-message__status me-2" role="status">✓ wird gespeichert</span>');
                }
            }, {once: true});
        });
    });
}());
