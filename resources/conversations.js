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

    function insertEditorNewline(composer) {
        const input = editor(composer);
        if (!input) { return; }
        if (input.value !== undefined) {
            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? start;
            input.setRangeText('\n', start, end, 'end');
            input.dispatchEvent(new Event('input', {bubbles: true}));
            return;
        }
        input.focus();
        // Let HumHub's ProseMirror editor handle precisely the same shortcut
        // as a physical Shift+Enter. Direct execCommand() calls are ignored by
        // this editor and previously left only whitespace in the draft.
        const newlineEvent = new KeyboardEvent('keydown', {
            key: 'Enter',
            code: 'Enter',
            keyCode: 13,
            which: 13,
            shiftKey: true,
            bubbles: true,
            cancelable: true,
        });
        input.dispatchEvent(newlineEvent);
        if (!newlineEvent.defaultPrevented) {
            document.execCommand('insertHTML', false, '<br>');
        }
        input.dispatchEvent(new Event('input', {bubbles: true}));
    }

    function submitComposer(composer) {
        if (composer.dataset.conversationSubmitting === 'true' || editorText(composer) === '') { return; }
        const button = composer.querySelector('button[type="submit"]');
        if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true') { return; }
        const input = editor(composer);
        // HumHub serializes ProseMirror into its hidden textarea on focusout.
        // A physical button click moves the focus first; button.click() does
        // not. Synchronize explicitly so Enter never posts an empty message.
        if (input && input.value === undefined) { input.blur(); }
        button.click();
    }

    function handleComposerShortcut(event) {
        if (event.key !== 'Enter' || event.isComposing || event.altKey) { return; }
        const target = event.target;
        if (!(target instanceof Element)) { return; }
        const composer = target.closest('.conversation-composer');
        if (!composer || !target.matches('[contenteditable="true"], textarea[name="ConversationMessage[message]"]')) { return; }

        const sendWithCtrlEnter = composer.dataset.conversationSendWithCtrlEnter === 'true';
        const modifierPressed = event.ctrlKey || event.metaKey;
        const shouldSend = sendWithCtrlEnter ? modifierPressed && !event.shiftKey : !modifierPressed && !event.shiftKey;
        if (!shouldSend && !(modifierPressed && !event.shiftKey)) { return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (shouldSend) {
            if (!event.repeat) { submitComposer(composer); }
            return;
        }
        insertEditorNewline(composer);
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

    function setReply(composer, reply) {
        const hidden = composer?.querySelector('[data-conversation-reply-input]');
        const preview = composer?.querySelector('[data-conversation-reply-preview]');
        const replyId = Number(reply?.dataset.conversationReplyId || 0);
        if (!composer || !hidden || !preview || !Number.isInteger(replyId) || replyId < 1) { return false; }

        hidden.value = String(replyId);
        preview.hidden = false;
        // Dataset values come from names and message excerpts. Construct the
        // preview with text nodes so they can never become markup.
        const author = document.createElement('strong');
        author.textContent = 'Antwort an ' + (reply.dataset.conversationReplyAuthor || '');
        const excerpt = document.createElement('span');
        excerpt.textContent = reply.dataset.conversationReplyExcerpt || '';
        const clear = document.createElement('button');
        clear.type = 'button'; clear.setAttribute('aria-label', 'Antwortbezug entfernen'); clear.textContent = '×';
        clear.addEventListener('click', function () { hidden.value = ''; preview.hidden = true; preview.textContent = ''; }, {once: true});
        preview.replaceChildren(author, excerpt, clear);
        return true;
    }

    function flashMessage(id) {
        const message = document.getElementById(id);
        if (!message) { return; }
        scrollIntoReadableArea(message, 'center');
        message.classList.remove('conversation-message--flash');
        window.setTimeout(function () { message.classList.add('conversation-message--flash'); }, 20);
        window.setTimeout(function () { message.classList.remove('conversation-message--flash'); }, 1800);
    }

    /** Scrolls a message into the part of the viewport that is not covered by the sticky composer. */
    function scrollIntoReadableArea(target, alignment = 'center', behavior = 'smooth') {
        if (!target) { return; }
        const feed = target.closest('.conversation-view__feed');
        if (feed) {
            const feedRect = feed.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            const readableTop = feedRect.top + 12;
            const readableBottom = feedRect.bottom - 12;
            if (targetRect.top >= readableTop && targetRect.bottom <= readableBottom) { return; }

            const availableHeight = Math.max(1, readableBottom - readableTop);
            const visibleTargetHeight = Math.min(targetRect.height, Math.max(1, availableHeight - 24));
            const desiredTop = alignment === 'start'
                ? readableTop
                : alignment === 'end'
                    ? readableBottom - visibleTargetHeight
                    : readableTop + (availableHeight - visibleTargetHeight) / 2;
            feed.scrollBy({top: targetRect.top - desiredTop, behavior: behavior});
            return;
        }
        const composer = document.querySelector('.conversation-composer');
        const composerTop = composer?.getBoundingClientRect().top;
        // The composer is part of the document flow, but while it is sticky it
        // visually covers the bottom of the viewport. Reserve that exact area.
        const readableBottom = composerTop !== undefined && composerTop > 0 && composerTop < window.innerHeight
            ? composerTop - 12
            : window.innerHeight - 12;
        const readableTop = 12;
        const targetRect = target.getBoundingClientRect();
        if (targetRect.top >= readableTop && targetRect.bottom <= readableBottom) { return; }

        const availableHeight = Math.max(1, readableBottom - readableTop);
        const visibleTargetHeight = Math.min(targetRect.height, Math.max(1, availableHeight - 24));
        const desiredTop = alignment === 'start'
            ? readableTop
            : alignment === 'end'
                ? readableBottom - visibleTargetHeight
                : readableTop + (availableHeight - visibleTargetHeight) / 2;
        window.scrollBy({top: targetRect.top - desiredTop, behavior: behavior});
    }

    function appendNewMessages(view, html) {
        const source = new DOMParser().parseFromString(html, 'text/html').querySelector('.conversation-view');
        const targetMessages = view.querySelector('.conversation-view__messages');
        const sourceMessages = source?.querySelector('.conversation-view__messages');
        if (!source || !targetMessages || !sourceMessages) { return false; }

        const feed = view.querySelector('.conversation-view__feed');
        const followMessages = !feed || feed.scrollHeight - feed.scrollTop - feed.clientHeight < 48;
        const knownId = Number(view.dataset.conversationLatestMessageId || 0);
        const items = Array.from(sourceMessages.children);
        let firstNewIndex = items.findIndex(function (item) {
            return item.classList.contains('conversation-message')
                && Number((item.id || '').replace('conversation-message-', '')) > knownId;
        });
        if (firstNewIndex === -1) { return false; }
        if (firstNewIndex > 0 && items[firstNewIndex - 1].classList.contains('conversation-date-divider')) { firstNewIndex--; }

        const appended = [];
        items.slice(firstNewIndex).forEach(function (item) {
            const clone = document.importNode(item, true);
            targetMessages.appendChild(clone);
            if (clone.classList.contains('conversation-message')) { appended.push(clone); }
        });
        const latestId = source.dataset.conversationLatestMessageId;
        if (latestId) { view.dataset.conversationLatestMessageId = latestId; }
        if (followMessages) {
            scrollToNewMessages(appended);
        } else {
            showNewMessageNotice(view);
        }
        return appended.length > 0;
    }

    function showNewMessageNotice(view) {
        let notice = view.querySelector('[data-conversation-new-message-notice]');
        if (!notice) {
            notice = document.createElement('button');
            notice.type = 'button';
            notice.className = 'conversation-new-message-notice btn btn-primary btn-sm';
            notice.dataset.conversationNewMessageNotice = 'true';
            notice.addEventListener('click', function () {
                const latest = view.querySelector('.conversation-view__messages > .conversation-message:last-of-type');
                scrollIntoReadableArea(latest, 'end');
                notice.remove();
            });
            view.appendChild(notice);
        }
        notice.textContent = 'Neue Nachrichten ↓';
    }

    function scrollToNewMessages(messages) {
        if (messages.length === 0) { return; }
        // Show the entire new-message run when it fits. Otherwise begin with
        // the oldest of the newest messages that fit into one viewport.
        const feed = messages[0]?.closest('.conversation-view__feed');
        const viewportHeight = feed?.clientHeight || window.innerHeight;
        let requiredHeight = 0;
        let firstVisible = messages.at(-1);
        for (let index = messages.length - 1; index >= 0; index--) {
            const item = messages[index];
            const style = window.getComputedStyle(item);
            const itemHeight = item.getBoundingClientRect().height
                + parseFloat(style.marginTop || '0') + parseFloat(style.marginBottom || '0');
            if (requiredHeight > 0 && requiredHeight + itemHeight > viewportHeight) { break; }
            requiredHeight += itemHeight;
            firstVisible = item;
        }
        scrollIntoReadableArea(firstVisible, 'start');
    }

    function showNewMessages(view) {
        if (view.dataset.conversationLoadingMessages === 'true' || !view.dataset.conversationLiveMessagesUrl) { return; }
        view.dataset.conversationLoadingMessages = 'true';
        window.fetch(view.dataset.conversationLiveMessagesUrl, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) { return response.ok ? response.text() : ''; })
            .then(function (html) { if (html) { appendNewMessages(view, html); } })
            .catch(function () { /* Keep the next socket/poll attempt available after a temporary failure. */ })
            .finally(function () { delete view.dataset.conversationLoadingMessages; });
    }

    function typingText(users) {
        const names = users.map(function (user) { return user.name; }).filter(Boolean);
        if (names.length === 0) { return ''; }
        if (names.length === 1) { return names[0] + ' tippt …'; }
        if (names.length === 2) { return names[0] + ' und ' + names[1] + ' tippen …'; }
        return names.slice(0, -1).join(', ') + ' und ' + names.at(-1) + ' tippen …';
    }

    function initializeTyping(view) {
        const composer = document.querySelector('.conversation-composer');
        const indicator = view.querySelector('[data-conversation-typing]');
        if (!composer || !indicator || !view.dataset.conversationTypingUrl || !view.dataset.conversationTypingStateUrl) { return; }
        let isTyping = false;
        let lastHeartbeat = 0;
        const csrf = composer.querySelector('input[name^="_csrf"]');
        const report = function (nextTyping) {
            const now = Date.now();
            if (nextTyping === isTyping && (!nextTyping || now - lastHeartbeat < 2500)) { return; }
            isTyping = nextTyping;
            lastHeartbeat = now;
            const body = new URLSearchParams({typing: nextTyping ? '1' : '0'});
            if (csrf?.name && csrf.value) { body.set(csrf.name, csrf.value); }
            window.fetch(view.dataset.conversationTypingUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                body: body.toString(),
            }).catch(function () { /* A missing indicator must never interrupt writing. */ });
        };
        const refresh = function () {
            if (document.hidden) { return; }
            window.fetch(view.dataset.conversationTypingStateUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (state) {
                    const text = typingText(state?.users || []);
                    indicator.textContent = text;
                    indicator.hidden = text === '';
                }).catch(function () { /* The chat remains usable if a heartbeat is unavailable. */ });
        };
        composer.addEventListener('input', function () { report(editorText(composer) !== ''); });
        editor(composer)?.addEventListener('blur', function () { report(false); });
        document.addEventListener('visibilitychange', function () { if (document.hidden) { report(false); } else { refresh(); } });
        refresh();
        window.setInterval(refresh, 2000);
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
        const conversationView = document.querySelector('[data-conversation-live-url]');
        // HumHub can replace just the content area when navigating back to the
        // overview. Keep the focused layout strictly scoped to an open chat.
        document.body.classList.toggle('conversation-page', Boolean(conversationView));
        if (conversationView) {
            const syncViewport = function () {
                const topbars = Array.from(document.querySelectorAll('#topbar-first, #topbar-second'));
                const topOffset = Math.max(0, ...topbars.map(function (bar) { return bar.getBoundingClientRect().bottom; }));
                document.documentElement.style.setProperty('--conversation-top-offset', Math.ceil(topOffset) + 'px');
                const composer = document.querySelector('.conversation-composer');
                document.documentElement.style.setProperty('--conversation-composer-height', Math.ceil(composer?.getBoundingClientRect().height || 0) + 'px');
            };
            syncViewport();
            window.addEventListener('resize', syncViewport, {passive: true});
            const composer = document.querySelector('.conversation-composer');
            if (composer && window.ResizeObserver) { new ResizeObserver(syncViewport).observe(composer); }
        }
        const latestMessage = document.querySelector('.conversation-view__messages > .conversation-message:last-of-type');
        if (!window.location.hash && latestMessage) {
            window.requestAnimationFrame(function () {
                // Opening a chat is the reading-now case: show the newest
                // message directly above the sticky composer. The unread
                // divider remains available through the navigation control.
                scrollIntoReadableArea(latestMessage, 'end', 'auto');
            });
        }
        const highlightHashTarget = function () {
            if (!window.location.hash.startsWith('#conversation-message-')) { return; }
            // Browsers resolve anchors before the flex-based chat surface has
            // finished sizing. Reposition afterwards so the target is never
            // left underneath the composer or at an arbitrary page position.
            window.requestAnimationFrame(function () {
                flashMessage(window.location.hash.slice(1));
            });
        };
        highlightHashTarget();
        window.addEventListener('hashchange', highlightHashTarget);

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
            const composer = document.querySelector('.conversation-composer');
            const key = composer?.dataset.conversationDraftKey;
            const editorId = composer?.dataset.conversationEditorId;
            const editorElement = editorId ? document.getElementById(editorId) : null;
            const clearRichTextBackup = function () {
                try {
                    const backupKey = 'RichTextEditor.backup';
                    const backup = JSON.parse(window.sessionStorage.getItem(backupKey) || '{}');
                    // RichText uses the hidden textarea ID as the backup key.
                    // Each conversation has its own editor ID, so drafts cannot
                    // leak into another chat.
                    if (editorId) { delete backup[editorId + '_input']; }
                    // Clean up the shared key written by earlier plugin versions.
                    delete backup['conversation-message-editor_input'];
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
                const input = composer ? editor(composer) : null;
                if (!input || input.value !== undefined || !composer || editorText(composer) === '') { return; }
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
                if (composer && editorText(composer) !== '' && clearAttempts++ < 10) {
                    window.setTimeout(clearAfterRichTextReady, 50);
                }
            };
            window.setTimeout(clearAfterRichTextReady, 50);
            if (key) { window.localStorage.removeItem(key); }
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
                if (!setReply(composer, reply)) { return; }
                composer.scrollIntoView({block: 'center', behavior: 'smooth'});
                editor(composer)?.focus();
                return;
            }
            const jump = event.target.closest('[data-conversation-jump-message]');
            if (jump) { event.preventDefault(); flashMessage(jump.dataset.conversationJumpMessage); return; }
            const navigation = event.target.closest('[data-conversation-jump]');
            if (navigation) {
                const target = navigation.dataset.conversationJump === 'unread' ? document.getElementById('first-unread-message') : document.querySelector('.conversation-view__messages > .conversation-message:last-of-type');
                scrollIntoReadableArea(target, 'end');
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
                // Quoting only part of a message still creates a reply to the
                // complete source message, preserving the conversation context.
                setReply(composer, anchor.closest('.conversation-message'));
                button.remove(); composer.scrollIntoView({block: 'center', behavior: 'smooth'});
            }, {once: true});
            anchor.appendChild(button);
            window.setTimeout(function () { button.remove(); }, 5000);
        });

        document.addEventListener('keydown', handleComposerShortcut, true);

        if (conversationView) {
            // A configured socket relay notifies this browser immediately. The
            // polling path below remains the safe fallback for every setup.
            connectRealtime(conversationView);
            initializeTyping(conversationView);
            window.setInterval(function () {
                if (document.hidden) { return; }
                window.fetch(conversationView.dataset.conversationLiveUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state || Number(state.latestMessageId) <= Number(conversationView.dataset.conversationLatestMessageId)) { return; }
                        showNewMessages(conversationView);
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
