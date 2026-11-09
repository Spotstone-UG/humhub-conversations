// SPDX-License-Identifier: AGPL-3.0-only
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/conversations.js', 'utf8');
const start = source.indexOf('    function appendNewMessages(');
const end = source.indexOf('    function showNewMessageNotice(', start);

for (const follow of [true, false]) {
    const events = [], children = [];
    const makeNode = (id, message = true) => ({id, classList: {contains: name => name === (message ? 'conversation-message' : 'conversation-date-divider')}});
    const existing = makeNode('conversation-message-1');
    const fresh = makeNode('conversation-message-2');
    const divider = makeNode('', false);
    const target = {appendChild(node) {children.push(node); events.push(['insert', node]);}};
    const feed = {scrollHeight: 1000, scrollTop: follow ? 500 : 0, clientHeight: 500};
    const view = {dataset: {conversationLatestMessageId: '1'}, querySelector(selector) {
        return selector === '.conversation-view__messages' ? target : feed;
    }};
    const incoming = {dataset: {conversationLatestMessageId: '2'}, querySelector: () => ({children: [existing, divider, fresh]})};
    const context = {
        DOMParser: class {parseFromString() {return {querySelector: () => incoming};}},
        document: {body: {classList: {contains: () => false}}, importNode: node => ({...node})},
        window: {humhub: {require(name) {
            assert.equal(name, 'ui.additions');
            return {applyTo(node) {
                assert.ok(children.includes(node), 'Initialize only after insertion into the document');
                events.push(['render', node]);
            }};
        }}},
        scrollToNewMessages(nodes) {events.push(['scroll', nodes]);},
        showNewMessageNotice() {events.push(['notice']);},
    };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    assert.equal(context.appendNewMessages(view, '<html>'), true);
    assert.equal(children.length, 2, 'Keep the date separator and new message');
    const rendered = events.filter(([type]) => type === 'render').map(([, node]) => node);
    assert.deepEqual(rendered, children, 'Initialize precisely the imported nodes, not existing messages or the composer');
    assert.equal(events.at(-1)[0], follow ? 'scroll' : 'notice', 'Render before calculating the scroll position');
    assert.equal(view.dataset.conversationLatestMessageId, '2');
    const previousEvents = events.length;
    assert.equal(context.appendNewMessages(view, '<html>'), false);
    assert.equal(events.length, previousEvents, 'Repeated delivery must not initialize existing widgets again');
}
console.log('PASS: live messages initialize HumHub RichText after insertion and before scrolling; existing messages and composer remain untouched.');
