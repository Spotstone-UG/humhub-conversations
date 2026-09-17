/* SPDX-License-Identifier: AGPL-3.0-only */
import crypto from 'node:crypto';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const MAX_BODY_BYTES = 4096;
const MAX_FRAME_BYTES = 65536;

function base64UrlDecode(value) {
    return Buffer.from(value.replace(/-/g, '+').replace(/_/g, '/'), 'base64');
}

function safeEqual(left, right) {
    const a = Buffer.from(left);
    const b = Buffer.from(right);
    return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function parseTicket(value, secret) {
    const [payload, signature, extra] = String(value || '').split('.');
    if (!payload || !signature || extra || !/^[0-9a-f]{64}$/i.test(signature)) return null;
    const expected = crypto.createHmac('sha256', secret).update(payload).digest('hex');
    if (!safeEqual(signature, expected)) return null;
    try {
        const ticket = JSON.parse(base64UrlDecode(payload).toString('utf8'));
        if (ticket.v !== 1 || !Number.isInteger(ticket.u) || !Number.isInteger(ticket.c) || !Number.isInteger(ticket.e) || ticket.e < Math.floor(Date.now() / 1000)) return null;
        return ticket;
    } catch {
        return null;
    }
}

function frame(payload, opcode = 0x1) {
    const body = Buffer.isBuffer(payload) ? payload : Buffer.from(payload);
    if (body.length >= 65536) throw new Error('WebSocket frame too large');
    if (body.length < 126) return Buffer.concat([Buffer.from([0x80 | opcode, body.length]), body]);
    const header = Buffer.alloc(4);
    header[0] = 0x80 | opcode;
    header[1] = 126;
    header.writeUInt16BE(body.length, 2);
    return Buffer.concat([header, body]);
}

function consumeFrames(socket, buffer) {
    let offset = 0;
    while (buffer.length - offset >= 2) {
        const first = buffer[offset];
        const second = buffer[offset + 1];
        let length = second & 0x7f;
        let headerLength = 2;
        if (length === 126) {
            if (buffer.length - offset < 4) break;
            length = buffer.readUInt16BE(offset + 2);
            headerLength = 4;
        } else if (length === 127 || length > MAX_FRAME_BYTES) {
            socket.destroy();
            return Buffer.alloc(0);
        }
        if ((second & 0x80) === 0) { socket.destroy(); return Buffer.alloc(0); }
        const total = headerLength + 4 + length;
        if (buffer.length - offset < total) break;
        const opcode = first & 0x0f;
        const maskOffset = offset + headerLength;
        const payloadOffset = maskOffset + 4;
        if (opcode === 0x8) { socket.end(frame('', 0x8)); return Buffer.alloc(0); }
        if (opcode === 0x9) socket.write(frame(buffer.subarray(payloadOffset, payloadOffset + length), 0xA));
        offset += total;
    }
    return buffer.subarray(offset);
}

function sendJson(socket, value) {
    if (!socket.destroyed) socket.write(frame(JSON.stringify(value)));
}

/**
 * Minimal dependency-free WebSocket relay. It authenticates a short-lived
 * ticket issued by Conversations and distributes identifier-only events.
 */
export function createRealtimeServer({ secret, allowedOrigins = [], websocketPath = '/conversations-realtime' }) {
    if (typeof secret !== 'string' || secret.length < 32) throw new Error('CONVERSATIONS_REALTIME_SECRET must contain at least 32 characters.');
    const clients = new Map();
    const allowed = new Set(allowedOrigins.filter(Boolean));

    const server = http.createServer((request, response) => {
        if (request.method === 'GET' && request.url === '/healthz') {
            response.writeHead(200, {'Content-Type': 'application/json'});
            response.end(JSON.stringify({ok: true}));
            return;
        }
        if (request.method !== 'POST' || request.url !== '/publish') {
            response.writeHead(404); response.end(); return;
        }
        let body = Buffer.alloc(0);
        request.on('data', (chunk) => {
            body = Buffer.concat([body, chunk]);
            if (body.length > MAX_BODY_BYTES) request.destroy();
        });
        request.on('end', () => {
            const signature = request.headers['x-conversations-signature'];
            const expected = crypto.createHmac('sha256', secret).update(body).digest('hex');
            if (typeof signature !== 'string' || !safeEqual(signature, expected)) {
                response.writeHead(401); response.end(); return;
            }
            try {
                const event = JSON.parse(body.toString('utf8'));
                if (event.event !== 'conversation.message.created' || !Number.isInteger(event.conversationId) || !Number.isInteger(event.messageId) || !Number.isInteger(event.timestamp) || Math.abs(Math.floor(Date.now() / 1000) - event.timestamp) > 60) throw new Error('invalid event');
                const recipients = clients.get(event.conversationId) || new Set();
                const message = {type: event.event, conversationId: event.conversationId, messageId: event.messageId};
                recipients.forEach((socket) => sendJson(socket, message));
                response.writeHead(202, {'Content-Type': 'application/json'});
                response.end(JSON.stringify({delivered: recipients.size}));
            } catch {
                response.writeHead(400); response.end();
            }
        });
    });

    server.on('upgrade', (request, socket) => {
        const url = new URL(request.url, 'http://localhost');
        const origin = request.headers.origin;
        const protocols = String(request.headers['sec-websocket-protocol'] || '').split(',').map((value) => value.trim());
        const ticket = url.pathname === websocketPath && typeof origin === 'string' && allowed.has(origin) && protocols[0] === 'conversations-v1' ? parseTicket(protocols[1], secret) : null;
        if (!ticket || request.headers.upgrade?.toLowerCase() !== 'websocket' || typeof request.headers['sec-websocket-key'] !== 'string') {
            socket.write('HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n'); socket.destroy(); return;
        }
        const accept = crypto.createHash('sha1').update(request.headers['sec-websocket-key'] + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11').digest('base64');
        socket.write('HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: ' + accept + '\r\nSec-WebSocket-Protocol: conversations-v1\r\n\r\n');
        const room = clients.get(ticket.c) || new Set();
        room.add(socket); clients.set(ticket.c, room);
        let input = Buffer.alloc(0);
        socket.on('data', (chunk) => { input = consumeFrames(socket, Buffer.concat([input, chunk])); });
        socket.on('close', () => { room.delete(socket); if (room.size === 0) clients.delete(ticket.c); });
        socket.on('error', () => {});
    });
    // server.close() does not close upgraded WebSocket connections itself.
    // Close them explicitly so systemd can stop the relay without waiting for
    // each browser connection to time out.
    server.closeRealtime = (callback) => {
        clients.forEach((room) => room.forEach((socket) => socket.destroy()));
        server.close(callback);
    };
    return server;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1])) {
    const port = Number.parseInt(process.env.CONVERSATIONS_REALTIME_PORT || '8091', 10);
    const host = process.env.CONVERSATIONS_REALTIME_HOST || '127.0.0.1';
    const allowedOrigins = (process.env.CONVERSATIONS_REALTIME_ALLOWED_ORIGINS || '').split(',').map((value) => value.trim()).filter(Boolean);
    const websocketPath = process.env.CONVERSATIONS_REALTIME_PATH || '/conversations-realtime';
    const server = createRealtimeServer({secret: process.env.CONVERSATIONS_REALTIME_SECRET, allowedOrigins, websocketPath});
    server.listen(port, host, () => console.log(`Conversations real-time relay listening on ${host}:${port}${websocketPath}`));
    const stop = () => server.closeRealtime(() => process.exit(0));
    process.on('SIGINT', stop); process.on('SIGTERM', stop);
}
