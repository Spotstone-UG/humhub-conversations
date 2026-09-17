/* SPDX-License-Identifier: AGPL-3.0-only */
(function () {
    'use strict';

    function editor(composer) {
        return composer.querySelector('[contenteditable="true"]') || composer.querySelector('textarea[name="ConversationMessage[message]"]');
    }

    function editorText(composer) {
        const input = editor(composer);
        if (!input) { return ''; }
        if (input.value !== undefined) { return input.value.trim(); }
        const visible = input.cloneNode(true);
        visible.querySelectorAll('.placeholder, [contenteditable="false"], .ProseMirror-separator, .ProseMirror-trailingBreak').forEach(function (item) { item.remove(); });
        return visible.textContent.trim();
    }

    function setEditorText(composer, text) {
        const input = editor(composer);
        if (!input) { return; }
        if (input.value !== undefined) {
            input.value = text;
            input.dispatchEvent(new Event('input', {bubbles: true}));
        } else {
            input.focus();
            document.execCommand('insertText', false, text);
            input.dispatchEvent(new Event('input', {bubbles: true}));
        }
    }

    function flashMessage(id) {
        const message = document.getElementById(id);
        if (!message) { return; }
        message.scrollIntoView({block: 'center', behavior: 'smooth'});
        message.classList.remove('conversation-message--flash');
        window.setTimeout(function () { message.classList.add('conversation-message--flash'); }, 20);
        window.setTimeout(function () { message.classList.remove('conversation-message--flash'); }, 1800);
    }

    function initializeConversationUi() {
        const firstUnread = document.getElementById('first-unread-message');
        const resumeMessage = document.querySelector('[data-conversation-resume]');
        if (!window.location.hash && (resumeMessage || firstUnread)) {
            window.requestAnimationFrame(function () {
                (resumeMessage || firstUnread).scrollIntoView({block: 'center'});
            });
        }
        if (window.location.hash.startsWith('#conversation-message-')) { flashMessage(window.location.hash.slice(1)); }

        document.querySelectorAll('.conversation-composer').forEach(function (composer) {
            const key = composer.dataset.conversationDraftKey;
            const saved = key ? window.localStorage.getItem(key) : null;
            if (saved && editorText(composer) === '') { setEditorText(composer, saved); }
            composer.addEventListener('input', function () {
                if (key) { window.localStorage.setItem(key, editorText(composer)); }
            });
            composer.addEventListener('submit', function () {
                const button = composer.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.insertAdjacentHTML('beforebegin', '<span class="conversation-message__status me-2" role="status">✓ wird gespeichert</span>');
                }
                if (key) { window.localStorage.removeItem(key); }
            }, {once: true});
            const clearAfterExplicitSend = function () {
                // HumHub's rich-text form may submit asynchronously before the
                // browser fires the form event. Clear both local draft stores at
                // the user's explicit send action so sent text never reappears.
                if (key && editorText(composer) !== '') { window.localStorage.removeItem(key); }
            };
            composer.addEventListener('click', function (event) {
                if (event.target.closest('button[type="submit"], button.btn-primary')) { clearAfterExplicitSend(); }
            }, true);
            // ProseMirror keeps its own editable element. Polling its visible
            // value makes the local recovery independent from editor internals.
            if (key) {
                window.setInterval(function () {
                    const text = editorText(composer);
                    if (text !== '') { window.localStorage.setItem(key, text); }
                }, 1000);
            }
        });

        if (new URLSearchParams(window.location.search).get('draftSent') === '1') {
            // This runs only after the server has persisted the message. It is
            // therefore safe to clear HumHub's local Markdown backup now.
            if (window.jQuery) {
                window.jQuery('#conversation-message-editor').trigger('clear');
            } else {
                document.getElementById('conversation-message-editor')?.dispatchEvent(new Event('clear', {bubbles: true}));
            }
            window.localStorage.removeItem('conversation-draft-' + new URLSearchParams(window.location.search).get('id'));
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('draftSent');
            window.history.replaceState({}, '', cleanUrl.toString());
        }

        document.addEventListener('click', function (event) {
            const reactionTab = event.target.closest('[data-conversation-reaction-category]');
            if (reactionTab) {
                const picker = reactionTab.closest('.conversation-reaction-picker');
                const category = reactionTab.dataset.conversationReactionCategory;
                picker?.querySelectorAll('[data-conversation-reaction-category]').forEach(function (item) { item.classList.toggle('is-active', item === reactionTab); });
                picker?.querySelectorAll('[data-conversation-reaction-category-panel]').forEach(function (panel) { panel.hidden = panel.dataset.conversationReactionCategoryPanel !== category; });
                const heading = picker?.querySelector('[data-conversation-reaction-heading]');
                if (heading) { heading.textContent = reactionTab.getAttribute('aria-label') || ''; }
                return;
            }
            const reply = event.target.closest('[data-conversation-reply-id]');
            if (reply) {
                event.preventDefault();
                const composer = document.querySelector('.conversation-composer');
                const hidden = composer?.querySelector('[data-conversation-reply-input]');
                const preview = composer?.querySelector('[data-conversation-reply-preview]');
                if (!composer || !hidden || !preview) { return; }
                hidden.value = reply.dataset.conversationReplyId;
                preview.hidden = false;
                // Dataset values come from names and message excerpts. Construct
                // the preview with text nodes so they can never become markup.
                const author = document.createElement('strong');
                author.textContent = 'Antwort an ' + (reply.dataset.conversationReplyAuthor || '');
                const excerpt = document.createElement('span');
                excerpt.textContent = reply.dataset.conversationReplyExcerpt || '';
                const clear = document.createElement('button');
                clear.type = 'button'; clear.setAttribute('aria-label', 'Antwortbezug entfernen'); clear.textContent = '×';
                clear.addEventListener('click', function () { hidden.value = ''; preview.hidden = true; preview.textContent = ''; }, {once: true});
                preview.replaceChildren(author, excerpt, clear);
                composer.scrollIntoView({block: 'center', behavior: 'smooth'});
                editor(composer)?.focus();
                return;
            }
            const jump = event.target.closest('[data-conversation-jump-message]');
            if (jump) { event.preventDefault(); flashMessage(jump.dataset.conversationJumpMessage); return; }
            const navigation = event.target.closest('[data-conversation-jump]');
            if (navigation) {
                const target = navigation.dataset.conversationJump === 'unread' ? document.getElementById('first-unread-message') : document.querySelector('.conversation-view__messages > .conversation-message:last-of-type');
                target?.scrollIntoView({block: 'end', behavior: 'smooth'});
            }
            const filter = event.target.closest('[data-conversation-filter]');
            if (filter) {
                const current = filter.dataset.conversationFilter;
                document.querySelectorAll('[data-conversation-filter]').forEach(function (button) { button.classList.toggle('is-active', button === filter); });
                document.querySelectorAll('.conversation-global-overview__space:not(.conversation-global-overview__space--muted) .conversation-overview__row').forEach(function (row) {
                    row.hidden = current !== 'all' && (current === 'unread' ? row.dataset.conversationUnread !== 'true' : row.dataset.conversationStatus !== current);
                });
                return;
            }
            if (event.target.closest('[data-conversation-open-muted]')) {
                const muted = document.querySelector('[data-conversation-muted-spaces]');
                if (muted) { muted.open = true; muted.scrollIntoView({block: 'center', behavior: 'smooth'}); }
            }
        });

        document.addEventListener('input', function (event) {
            const search = event.target;
            if (search instanceof HTMLInputElement && search.matches('[data-conversation-reaction-search]')) {
                const term = search.value.trim().toLocaleLowerCase();
                const picker = search.closest('.conversation-reaction-picker');
                const activeTab = picker?.querySelector('[data-conversation-reaction-category].is-active');
                picker?.querySelectorAll('[data-conversation-reaction-category-panel]').forEach(function (panel) { panel.hidden = term === '' && panel.dataset.conversationReactionCategoryPanel !== activeTab?.dataset.conversationReactionCategory; });
                picker?.querySelectorAll('[data-conversation-reaction-name]').forEach(function (item) { item.hidden = term !== '' && !item.dataset.conversationReactionName.toLocaleLowerCase().includes(term); });
                return;
            }
            if (search instanceof HTMLInputElement && search.matches('[data-conversation-search]')) {
                const term = search.value.trim().toLocaleLowerCase();
                document.querySelectorAll('.conversation-message').forEach(function (message) { message.hidden = term !== '' && !message.textContent.toLocaleLowerCase().includes(term); });
            }
            if (search instanceof HTMLInputElement && search.matches('[data-conversation-overview-search]')) {
                const term = search.value.trim().toLocaleLowerCase();
                document.querySelectorAll('.conversation-global-overview__space:not(.conversation-global-overview__space--muted) .conversation-overview__row').forEach(function (row) {
                    row.hidden = term !== '' && !row.textContent.toLocaleLowerCase().includes(term);
                });
            }
        });

        document.addEventListener('mouseup', function () {
            const selection = window.getSelection();
            const text = selection ? selection.toString().trim() : '';
            const anchor = selection?.anchorNode?.parentElement?.closest('.conversation-message__content');
            if (!text || !anchor || document.querySelector('.conversation-selection-quote')) { return; }
            const composer = document.querySelector('.conversation-composer');
            if (!composer) { return; }
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'conversation-selection-quote btn btn-default btn-sm'; button.textContent = 'Auswahl zitieren';
            button.addEventListener('click', function () {
                const quote = text.split('\n').map(function (line) { return '> ' + line; }).join('\n');
                setEditorText(composer, (editorText(composer) ? editorText(composer) + '\n\n' : '') + quote + '\n\n');
                button.remove(); composer.scrollIntoView({block: 'center', behavior: 'smooth'}); editor(composer)?.focus();
            }, {once: true});
            anchor.appendChild(button);
            window.setTimeout(function () { button.remove(); }, 5000);
        });

        const view = document.querySelector('[data-conversation-live-url]');
        if (view) {
            window.setInterval(function () {
                if (document.hidden) { return; }
                window.fetch(view.dataset.conversationLiveUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state || Number(state.latestMessageId) <= Number(view.dataset.conversationLatestMessageId)) { return; }
                        const composer = document.querySelector('.conversation-composer');
                        if (composer && editorText(composer) !== '') {
                            if (!document.querySelector('.conversation-new-message-notice')) {
                                const notice = document.createElement('button');
                                notice.type = 'button'; notice.className = 'conversation-new-message-notice btn btn-primary btn-sm'; notice.textContent = 'Neue Nachrichten anzeigen';
                                notice.addEventListener('click', function () { window.location.reload(); }, {once: true}); document.body.appendChild(notice);
                            }
                            return;
                        }
                        window.location.reload();
                    }).catch(function () { /* A temporary network failure must never interrupt writing. */ });
            }, 12000);
        }

        const overview = document.querySelector('[data-conversation-overview-live-url]');
        if (overview) {
            window.setInterval(function () {
                if (document.hidden) { return; }
                window.fetch(overview.dataset.conversationOverviewLiveUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state) { return; }
                        if (String(state.revision) !== overview.dataset.conversationOverviewRevision || Number(state.unreadTotal) !== Number(overview.dataset.conversationOverviewUnread)) {
                            window.location.reload();
                        }
                    }).catch(function () { /* The manual overview remains usable offline. */ });
            }, 15000);
        }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initializeConversationUi, {once: true}); } else { initializeConversationUi(); }
}());
