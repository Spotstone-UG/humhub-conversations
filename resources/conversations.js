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

    function moveCaretToEnd(input) {
        input.focus();
        if (input.value !== undefined) {
            input.selectionStart = input.selectionEnd = input.value.length;
            return;
        }
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(input);
        range.collapse(false);
        selection?.removeAllRanges();
        selection?.addRange(range);
    }

    function appendMarkdownQuote(composer, text) {
        const input = editor(composer);
        if (!input) { return; }
        const quote = text.split('\n').map(function (line) { return '> ' + line; }).join('\n');
        const prefix = editorText(composer) ? '\n\n' : '';
        const insertion = prefix + quote + '\n\n';

        if (input.value !== undefined) {
            input.value += insertion;
            input.dispatchEvent(new Event('input', {bubbles: true}));
            moveCaretToEnd(input);
            return;
        }

        // Insert literal Markdown, rather than a visual-only quote node. The
        // two trailing line breaks leave the cursor in a fresh paragraph.
        moveCaretToEnd(input);
        document.execCommand('insertText', false, insertion);
        input.dispatchEvent(new Event('input', {bubbles: true}));
        window.requestAnimationFrame(function () { moveCaretToEnd(input); });
    }

    function flashMessage(id) {
        const message = document.getElementById(id);
        if (!message) { return; }
        message.scrollIntoView({block: 'center', behavior: 'smooth'});
        message.classList.remove('conversation-message--flash');
        window.setTimeout(function () { message.classList.add('conversation-message--flash'); }, 20);
        window.setTimeout(function () { message.classList.remove('conversation-message--flash'); }, 1800);
    }

    function showNewMessages(view) {
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
    }

    function connectRealtime(view) {
        const url = view.dataset.conversationRealtimeUrl;
        const token = view.dataset.conversationRealtimeToken;
        if (!url || !token || !window.WebSocket) { return; }
        let retryDelay = 1000;
        let retryTimer = null;
        const reconnect = function () {
            if (retryTimer || document.hidden) { return; }
            retryTimer = window.setTimeout(function () {
                retryTimer = null;
                open();
            }, retryDelay);
            retryDelay = Math.min(retryDelay * 2, 30000);
        };
        const open = function () {
            let socket;
            try { socket = new WebSocket(url, ['conversations-v1', token]); } catch (error) { reconnect(); return; }
            socket.onopen = function () { retryDelay = 1000; };
            socket.onmessage = function (event) {
                let update;
                try { update = JSON.parse(event.data); } catch (error) { return; }
                if (update.type !== 'conversation.message.created' || Number(update.messageId) <= Number(view.dataset.conversationLatestMessageId)) { return; }
                showNewMessages(view);
            };
            socket.onerror = function () { socket.close(); };
            socket.onclose = reconnect;
        };
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { reconnect(); }
        });
        open();
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
            const startSubmitting = function () {
                if (composer.dataset.conversationSubmitting === 'true') { return false; }
                composer.dataset.conversationSubmitting = 'true';
                const button = composer.querySelector('button[type="submit"]');
                if (button) {
                    button.setAttribute('aria-disabled', 'true');
                    button.classList.add('is-submitting');
                    button.insertAdjacentHTML('beforebegin', '<span class="conversation-message__status me-2" role="status">✓ wird gespeichert</span>');
                }
                if (key) { window.localStorage.removeItem(key); }
                return true;
            };
            composer.addEventListener('submit', function (event) {
                if (!startSubmitting()) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            }, true);
            const clearAfterExplicitSend = function () {
                // HumHub's rich-text form may submit asynchronously before the
                // browser fires the form event. Clear both local draft stores at
                // the user's explicit send action so sent text never reappears.
                if (key && editorText(composer) !== '') { window.localStorage.removeItem(key); }
            };
            composer.addEventListener('click', function (event) {
                if (!event.target.closest('button[type="submit"], button.btn-primary')) { return; }
                if (composer.dataset.conversationSubmitting === 'true') {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    return;
                }
                // Let the native submit event claim the first request. Some
                // HumHub rich-text integrations submit from this click handler;
                // the database token remains the authoritative race protection.
                clearAfterExplicitSend();
            }, true);
            composer.addEventListener('keydown', function (event) {
                // Keep Enter for Markdown paragraphs. Ctrl/Cmd+Enter follows
                // the familiar chat shortcut and uses the exact same button
                // path as a mouse click, including the duplicate-send guard.
                if (event.key !== 'Enter' || event.isComposing || (!event.ctrlKey && !event.metaKey)) { return; }
                event.preventDefault();
                event.stopImmediatePropagation();
                if (event.repeat || composer.dataset.conversationSubmitting === 'true' || editorText(composer) === '') { return; }
                const button = composer.querySelector('button[type="submit"]');
                if (button && !button.disabled && button.getAttribute('aria-disabled') !== 'true') { button.click(); }
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
            const editorElement = document.getElementById('conversation-message-editor');
            const clearRichTextBackup = function () {
                try {
                    const backupKey = 'RichTextEditor.backup';
                    const backup = JSON.parse(window.sessionStorage.getItem(backupKey) || '{}');
                    delete backup['conversation-message-editor'];
                    if (Object.keys(backup).length === 0) {
                        window.sessionStorage.removeItem(backupKey);
                    } else {
                        window.sessionStorage.setItem(backupKey, JSON.stringify(backup));
                    }
                } catch (error) { /* A malformed third-party backup must not block sending. */ }
            };
            const clearEditor = function () {
                if (window.jQuery) {
                    window.jQuery(editorElement).trigger('clear');
                } else {
                    editorElement?.dispatchEvent(new Event('clear', {bubbles: true}));
                }
            };
            const clearVisibleEditor = function () {
                const input = editor(document.querySelector('.conversation-composer'));
                if (!input || input.value !== undefined || editorText(document.querySelector('.conversation-composer')) === '') { return; }
                // This is the browser-native editing path, so ProseMirror updates
                // its document state even when the optional jQuery bridge is absent.
                input.focus();
                document.execCommand('selectAll', false);
                document.execCommand('delete', false);
                input.dispatchEvent(new Event('input', {bubbles: true}));
            };
            if (window.jQuery && editorElement) {
                // RichText registers its clear handler during its widget init.
                // On a fresh page, Conversations can run first; clearing again at
                // afterInit prevents ProseMirror from restoring the sent content.
                window.jQuery(editorElement).one('afterInit', clearEditor);
            }
            clearEditor();
            let clearAttempts = 0;
            const clearAfterRichTextReady = function () {
                clearEditor();
                clearVisibleEditor();
                clearRichTextBackup();
                // Asset bundles can initialize ProseMirror after DOM ready. Retry
                // briefly until its own clear handler has emptied the document and
                // reset its session backup.
                if (editorText(document.querySelector('.conversation-composer')) !== '' && clearAttempts++ < 10) {
                    window.setTimeout(clearAfterRichTextReady, 50);
                }
            };
            window.setTimeout(clearAfterRichTextReady, 50);
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
                appendMarkdownQuote(composer, text);
                button.remove(); composer.scrollIntoView({block: 'center', behavior: 'smooth'});
            }, {once: true});
            anchor.appendChild(button);
            window.setTimeout(function () { button.remove(); }, 5000);
        });

        const view = document.querySelector('[data-conversation-live-url]');
        if (view) {
            // A configured socket relay notifies this browser immediately. The
            // polling path below remains the safe fallback for every setup.
            connectRealtime(view);
            window.setInterval(function () {
                if (document.hidden) { return; }
                window.fetch(view.dataset.conversationLiveUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state || Number(state.latestMessageId) <= Number(view.dataset.conversationLatestMessageId)) { return; }
                        showNewMessages(view);
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
