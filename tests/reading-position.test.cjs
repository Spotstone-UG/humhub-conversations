// SPDX-License-Identifier: AGPL-3.0-only
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/conversations.js', 'utf8');
const start = source.indexOf('    function readingResumeTarget(');
const end = source.indexOf('    function initializeConversationUi()', start);
function scenario({unread = false, resume = false, hash = '', empty = false, precedingMessage = false} = {}) {
    const frames = [], calls = [], handlers = {};
    const latest = {name: 'latest'}, previous = {name: 'last-read', matches: () => true}, divider = {name: 'first-unread'};
    if (precedingMessage) { divider.previousElementSibling = {matches: () => false, previousElementSibling: previous}; }
    const feed = {scrollHeight: 5000, scrollTo(options) {calls.push(['bottom', options.top]);}};
    const view = {dataset: {}, isConnected: true, addEventListener(name, callback) {handlers[name] = callback;}, querySelectorAll: () => [], querySelector(selector) {
        if (selector === '#first-unread-message') return unread ? divider : null;
        if (selector === '[data-conversation-resume]') return resume ? previous : null;
        if (selector === '.conversation-view__feed') return feed;
        if (selector.includes('last-of-type')) return empty ? null : latest;
        return null;
    }};
    const context = {document: {body: {classList: {toggle() {}, contains() {return false;}}}, querySelector: () => view}, window: {location: {hash}, requestAnimationFrame: callback => frames.push(callback), addEventListener(name, callback) {handlers[name] = callback;}}, scrollIntoReadableArea: (target, alignment, behavior, alignVisible) => {assert.equal(alignVisible, true);calls.push([target.name, alignment]);}};
    vm.createContext(context);vm.runInContext(source.slice(start, end), context);
    context.initializeReadingPosition();
    const flush = () => {while (frames.length) frames.shift()();};
    return {calls, handlers, feed, view, flush, repeat: () => context.initializeReadingPosition()};
}
let test = scenario({unread: true, resume: true}); test.flush(); assert.deepEqual(test.calls, [['last-read', 'start']]);
test.repeat();test.flush();assert.equal(test.calls.length, 1);
test.handlers.pointerdown();test.handlers.load();test.flush();assert.equal(test.calls.length, 1, 'Late layout changes must not override user navigation');
test = scenario({resume: true});test.flush();assert.deepEqual(test.calls, [['bottom', 5000]]);
test.feed.scrollHeight = 6000;test.handlers.load();test.flush();assert.deepEqual(test.calls.at(-1), ['bottom', 6000]);
test = scenario({unread: true});test.flush();assert.deepEqual(test.calls, [['first-unread', 'start']]);
test = scenario({unread: true, precedingMessage: true});test.flush();assert.deepEqual(test.calls, [['last-read', 'start']], 'Resume the previous message even without a saved resume attribute and across date separators');
test = scenario({hash: '#conversation-message-5', unread: true, resume: true});test.flush();assert.equal(test.calls.length, 0);
test = scenario({empty: true});test.flush();assert.equal(test.calls.length, 0);
// Exercise the real geometry helper: a visible message must still move to the
// top on entry, without changing ordinary "reveal if hidden" behavior.
const geometryStart = source.indexOf('    function scrollIntoReadableArea(');
const geometryEnd = source.indexOf('    function appendNewMessages(', geometryStart);
for (const pageScroll of [false, true]) {
    const calls = [];
    const feed = {getBoundingClientRect: () => ({top: 100, bottom: 700}), scrollBy: options => calls.push(options)};
    const target = {closest: () => feed, getBoundingClientRect: () => ({top: 400, bottom: 480, height: 80})};
    const context = {document: {body: {classList: {contains: () => pageScroll}}, querySelector: () => ({getBoundingClientRect: () => ({top: 700})})}, window: {innerHeight: 900, scrollBy: options => calls.push(options)}};
    vm.createContext(context);vm.runInContext(source.slice(geometryStart, geometryEnd), context);
    context.scrollIntoReadableArea(target, 'start', 'auto');assert.equal(calls.length, 0);
    context.scrollIntoReadableArea(target, 'start', 'auto', true);
    assert.equal(calls.length, 1);assert.equal(calls[0].top, pageScroll ? 388 : 288);
}
console.log('PASS: visible last-read messages align on entry in feed and page layouts; date separators, anchors, user scrolling and empty chats are preserved.');
