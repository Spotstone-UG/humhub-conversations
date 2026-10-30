// SPDX-License-Identifier: AGPL-3.0-only
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/conversations.js', 'utf8');
const marker = source.indexOf('const reactionTab =');
const start = source.lastIndexOf("document.addEventListener('click'", marker);
const end = source.indexOf("document.addEventListener('input'", marker);
let handler, replies = 0, scrolls = 0;
const composer = {scrollIntoView() {scrolls++;}};
vm.runInNewContext(source.slice(start, end), {
  document: {addEventListener: (name, callback) => handler = callback, querySelector: () => composer},
  setReply() {replies++; return true;}, editor: () => ({focus() {}}),
});
function click(kind) {
  const event = {prevented: false, preventDefault() {this.prevented = true;}, target: {closest(selector) {
    if (selector === '[data-conversation-reply-id]') return {dataset: {conversationReplyId: '123'}};
    if (selector === '.conversation-message__reply-trigger[data-conversation-reply-id]' && kind === 'reply') return {dataset: {conversationReplyId: '123'}};
    return null;
  }}};
  handler(event);
  return event;
}
for (const kind of ['reaction', 'reaction-picker', 'message-text', 'message-menu']) {
  assert.equal(click(kind).prevented, false, kind + ' must keep its own default action');
  assert.equal(replies, 0, kind + ' must not set reply context');
  assert.equal(scrolls, 0, kind + ' must not scroll to composer');
}
assert.equal(click('reply').prevented, true);
assert.equal(replies, 1);
assert.equal(scrolls, 1);
console.log('PASS: reactions, picker, text and menu do not trigger replies; explicit reply still sets context and scrolls.');
