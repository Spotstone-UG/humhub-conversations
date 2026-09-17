/* SPDX-License-Identifier: AGPL-3.0-only */
(function () {
    'use strict';

    function initializeConversationUi() {
        document.querySelectorAll('#contentFormMenu [data-action-url]').forEach(function (entry) {
            const actionUrl = decodeURIComponent(entry.getAttribute('data-action-url') || '');
            if (actionUrl.indexOf('/post/post/create-form') !== -1) {
                const listEntry = entry.closest('li');
                if (listEntry) {
                    listEntry.remove();
                } else {
                    entry.remove();
                }
            }
        });

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

        document.addEventListener('click', function (event) {
            const tab = event.target.closest('[data-conversation-reaction-category]');
            if (!tab) {
                return;
            }

            const picker = tab.closest('.conversation-reaction-picker');
            const category = tab.dataset.conversationReactionCategory;
            picker?.querySelectorAll('[data-conversation-reaction-category]').forEach(function (item) {
                item.classList.toggle('is-active', item === tab);
            });
            picker?.querySelectorAll('[data-conversation-reaction-category-panel]').forEach(function (panel) {
                panel.hidden = panel.dataset.conversationReactionCategoryPanel !== category;
            });
            const heading = picker?.querySelector('[data-conversation-reaction-heading]');
            if (heading) {
                heading.textContent = tab.getAttribute('aria-label') || '';
            }
        });

        document.addEventListener('input', function (event) {
            const search = event.target;
            if (!(search instanceof HTMLInputElement) || !search.matches('[data-conversation-reaction-search]')) {
                return;
            }

            const term = search.value.trim().toLocaleLowerCase();
            const picker = search.closest('.conversation-reaction-picker');
            const activeTab = picker?.querySelector('[data-conversation-reaction-category].is-active');
            picker?.querySelectorAll('[data-conversation-reaction-category-panel]').forEach(function (panel) {
                panel.hidden = term === '' && panel.dataset.conversationReactionCategoryPanel !== activeTab?.dataset.conversationReactionCategory;
            });
            picker?.querySelectorAll('[data-conversation-reaction-name]').forEach(function (item) {
                item.hidden = term !== '' && !item.dataset.conversationReactionName.toLocaleLowerCase().includes(term);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeConversationUi, {once: true});
    } else {
        initializeConversationUi();
    }
}());
