// SPDX-License-Identifier: AGPL-3.0-only
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/conversations.js', 'utf8');
const start = source.indexOf('    function initializeReadingPosition()');
const end = source.indexOf('    function initializeConversationUi()', start);
function scenario({unread = false, resume = false, hash = '', empty = false} = {}) {
    const frames = [], calls = [], handlers = {};
    const latest = {name: 'latest'}, previous = {name: 'last-read'}, divider = {name: 'first-unread'};
    const feed = {scrollHeight: 5000, scrollTo(options) {calls.push(['bottom', options.top]);}};
    const view = {dataset: {}, isConnected: true, addEventListener(name, callback) {handlers[name] = callback;}, querySelectorAll: () => [], querySelector(selector) {
        if (selector === '#first-unread-message') return unread ? divider : null;
        if (selector === '[data-conversation-resume]') return resume ? previous : null;
        if (selector === '.conversation-view__feed') return feed;
        if (selector.includes('last-of-type')) return empty ? null : latest;
        return null;
    }};
    const context = {document: {body: {classList: {toggle() {}}}, querySelector: () => view}, window: {location: {hash}, requestAnimationFrame: callback => frames.push(callback), addEventListener(name, callback) {handlers[name] = callback;}}, scrollIntoReadableArea: (target, alignment) => calls.push([target.name, alignment])};
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
test = scenario({hash: '#conversation-message-5', unread: true, resume: true});test.flush();assert.equal(test.calls.length, 0);
test = scenario({empty: true});test.flush();assert.equal(test.calls.length, 0);
console.log('PASS: unread resumes last read; read chat reaches bottom; first visit starts at unread; anchors, user scrolling and empty chats are preserved.');
