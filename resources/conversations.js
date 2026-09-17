/* SPDX-License-Identifier: AGPL-3.0-only */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const firstUnread = document.getElementById('first-unread-message');
        if (firstUnread && !window.location.hash) {
            firstUnread.scrollIntoView({block: 'center'});
        }
    });
}());

