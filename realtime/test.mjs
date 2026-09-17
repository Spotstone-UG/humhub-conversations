/* SPDX-License-Identifier: AGPL-3.0-only */
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import http from 'node:http';
import net from 'node:net';
import test from 'node:test';
import { createRealtimeServer } from './server.mjs';

const secret = 'test-secret-that-is-longer-than-thirty-two-characters';
const base64url = (value) => Buffer.from(value).toString('base64url');
const ticket = (conversationId) => {
    const payload = base64url(JSON.stringify({v: 1, u: 7, c: conversationId, e: Math.floor(Date.now() / 1000) + 60}));
    return `${payload}.${crypto.createHmac('sha256', secret).update(payload).digest('hex')}`;
};
const listen = (server) => new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(server.address().port)));
const openSocket = (port, conversationId) => new Promise((resolve, reject) => {
    const socket = net.connect(port, '127.0.0.1');
    let buffer = Buffer.alloc(0);
    socket.once('connect', () => socket.write(`GET /conversations-realtime HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: ${crypto.randomBytes(16).toString('base64')}\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Protocol: conversations-v1, ${ticket(conversationId)}\r\nOrigin: http://localhost:8765\r\n\r\n`));
    socket.on('data', (chunk) => {
        buffer = Buffer.concat([buffer, chunk]);
        const marker = buffer.indexOf('\r\n\r\n');
        if (marker !== -1) {
            assert.match(buffer.subarray(0, marker).toString(), /101 Switching Protocols/);
            socket.removeAllListeners('data');
            resolve(socket);
        }
    });
    socket.once('error', reject);
});
const publish = (port, conversationId, messageId) => new Promise((resolve, reject) => {
    const body = JSON.stringify({event: 'conversation.message.created', conversationId, messageId, timestamp: Math.floor(Date.now() / 1000)});
    const request = http.request({host: '127.0.0.1', port, path: '/publish', method: 'POST', headers: {'Content-Type': 'application/json', 'X-Conversations-Signature': crypto.createHmac('sha256', secret).update(body).digest('hex')}}, (response) => {
        response.resume();
        response.once('end', () => resolve(response.statusCode));
    });
    request.on('error', reject); request.end(body);
});

test('only the matching conversation receives a signed event', {timeout: 3000}, async () => {
    const server = createRealtimeServer({secret, allowedOrigins: ['http://localhost:8765']});
    const port = await listen(server);
    const matching = await openSocket(port, 4);
    const other = await openSocket(port, 9);
    const received = new Promise((resolve) => matching.once('data', (data) => resolve(data)));
    assert.equal(await publish(port, 4, 99), 202);
    const frame = await received;
    assert.match(frame.subarray(2).toString(), /"messageId":99/);
    let otherReceived = false;
    other.once('data', () => { otherReceived = true; });
    await new Promise((resolve) => setTimeout(resolve, 30));
    assert.equal(otherReceived, false);
    matching.destroy(); other.destroy();
    await new Promise((resolve) => server.closeRealtime(resolve));
});
