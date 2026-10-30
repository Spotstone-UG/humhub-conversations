const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const listeners = {};
let dialog, submitted = 0, focused = 0;
class Element {
  constructor(tag) { this.tag = tag; this.children = []; this.events = {}; this.dataset = {}; }
  setAttribute() {}
  append(...nodes) { this.children.push(...nodes); }
  appendChild(node) { this.append(node); if (node.tag === 'dialog') dialog = node; }
  addEventListener(name, callback) { this.events[name] = callback; }
  showModal() { this.open = true; }
  close() { this.open = false; this.events.close(); }
  remove() { this.removed = true; }
  getBoundingClientRect() { return {left: 10, right: 300, top: 10, bottom: 300}; }
}
const button = new Element('button');
button.textContent = '👍 2';
button.dataset = {reactionUsers: JSON.stringify(['Anna', '<img src=x onerror=alert(1)>']), reactionAction: 'Auch so reagieren'};
button.form = {requestSubmit: submitter => { assert.equal(submitter, button); submitted++; }};
button.focus = () => focused++;
const document = {readyState: 'loading', body: new Element('body'), addEventListener: (name, cb) => {listeners[name] = cb;}, createElement: tag => new Element(tag), querySelector: () => dialog && !dialog.removed ? dialog : null};
vm.runInNewContext(fs.readFileSync('resources/conversations.js', 'utf8'), {document, window: {matchMedia: () => ({matches: false})}, HTMLDialogElement: Element});
function click() { const e = {target: {closest: () => button}, preventDefault() {this.prevented = true;}, stopImmediatePropagation() {}}; listeners.click(e); return e; }
listeners.pointerdown({pointerType: 'mouse'});
assert.equal(click().prevented, undefined);
assert.equal(dialog, undefined);
listeners.pointerdown({pointerType: 'touch'});
assert.equal(click().prevented, true);
assert.equal(submitted, 0);
assert.equal(dialog.children[1].children[0].textContent, 'Anna');
assert.equal(dialog.children[1].children[1].textContent, '<img src=x onerror=alert(1)>');
dialog.children[3].events.click();
assert.equal(submitted, 0);
assert.equal(focused, 1);
click();
dialog.children[2].events.click();
assert.equal(submitted, 1);
button.dataset.reactionAction = 'Eigene Reaktion entfernen';
click();
assert.equal(dialog.children[2].textContent, 'Eigene Reaktion entfernen');
dialog.events.click({target: dialog, clientX: 0, clientY: 0});
assert.equal(dialog.removed, true);
assert.equal(submitted, 1);
console.log('PASS: mouse preserves click; touch inspects without submit; names stay text; explicit action submits; close/outside preserve reactions.');
