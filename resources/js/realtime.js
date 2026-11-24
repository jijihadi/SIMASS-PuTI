import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function readMeta(name) {
    return document.head?.querySelector(`meta[name="${name}"]`)?.getAttribute('content')?.trim() ?? '';
}

const key = readMeta('reverb-key');
const host = readMeta('reverb-host') || window.location.hostname;
const scheme = readMeta('reverb-scheme') || (window.location.protocol === 'https:' ? 'https' : 'http');
const rawPort = readMeta('reverb-port');
const port = rawPort ? Number(rawPort) : (scheme === 'https' ? 443 : 80);
const csrfToken = readMeta('csrf-token');

let echo = null;
let connectionState = key ? 'connecting' : 'disabled';
const connectionListeners = new Set();

function notifyConnectionListeners() {
    const connected = connectionState === 'connected';
    connectionListeners.forEach((listener) => {
        try {
            listener(connected, connectionState);
        } catch (err) {
            console.error('[SimassRealtime] listener error:', err);
        }
    });
}

if (key) {
    const forceTLS = scheme === 'https';

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
        },
    });

    window.Echo = echo;

    const pusherConnection = echo.connector?.pusher?.connection;
    if (pusherConnection) {
        connectionState = pusherConnection.state || 'connecting';
        pusherConnection.bind('state_change', ({ current }) => {
            connectionState = current || 'disconnected';
            notifyConnectionListeners();
        });
    }
}

function registerConnectionCallback(callback) {
    if (typeof callback !== 'function') {
        return () => {};
    }

    connectionListeners.add(callback);
    callback(connectionState === 'connected', connectionState);

    return () => {
        connectionListeners.delete(callback);
    };
}

function safeListen(channel, eventName, handler) {
    if (typeof handler === 'function') {
        channel.listen(eventName, handler);
    }
}

window.SimassRealtime = {
    isConnected() {
        return connectionState === 'connected';
    },

    getState() {
        return connectionState;
    },

    onConnectionChange(callback) {
        return registerConnectionCallback(callback);
    },

    subscribeNoc({ onTicketCreated, onTicketUpdated, onNetworkAlert, onConnectionChange } = {}) {
        const stopConn = registerConnectionCallback(onConnectionChange);
        if (!echo) {
            return stopConn;
        }

        const channelName = 'noc.tickets';
        const channel = echo.private(channelName);
        safeListen(channel, '.ticket.created', onTicketCreated);
        safeListen(channel, '.ticket.status.updated', onTicketUpdated);
        safeListen(channel, '.network.alert.triggered', onNetworkAlert);

        return () => {
            stopConn();
            echo.leave(channelName);
        };
    },

    subscribeTicket(ticketId, { isStaff = false, onMessage, onStatusUpdated, onConnectionChange } = {}) {
        const stopConn = registerConnectionCallback(onConnectionChange);
        if (!echo || !ticketId) {
            return stopConn;
        }

        const publicChannelName = `ticket.${ticketId}`;
        const publicChannel = echo.private(publicChannelName);
        safeListen(publicChannel, '.ticket.message.posted', onMessage);
        safeListen(publicChannel, '.ticket.status.updated', onStatusUpdated);

        let internalChannelName = null;
        if (isStaff) {
            internalChannelName = `ticket.${ticketId}.internal`;
            const internalChannel = echo.private(internalChannelName);
            safeListen(internalChannel, '.ticket.message.posted', onMessage);
        }

        return () => {
            stopConn();
            echo.leave(publicChannelName);
            if (internalChannelName) {
                echo.leave(internalChannelName);
            }
        };
    },

    subscribeUser(userId, { onTicketCreated, onMessage, onStatusUpdated, onConnectionChange } = {}) {
        const stopConn = registerConnectionCallback(onConnectionChange);
        if (!echo || !userId) {
            return stopConn;
        }

        const channelName = `user.${userId}`;
        const channel = echo.private(channelName);
        safeListen(channel, '.ticket.created', onTicketCreated);
        safeListen(channel, '.ticket.message.posted', onMessage);
        safeListen(channel, '.ticket.status.updated', onStatusUpdated);

        return () => {
            stopConn();
            echo.leave(channelName);
        };
    },

    subscribePresence({ onPresenceRecorded, onConnectionChange } = {}) {
        const stopConn = registerConnectionCallback(onConnectionChange);
        if (!echo) {
            return stopConn;
        }

        const channelName = 'presence.live';
        const channel = echo.private(channelName);
        safeListen(channel, '.presence.recorded', onPresenceRecorded);

        return () => {
            stopConn();
            echo.leave(channelName);
        };
    },

    subscribeInventory({ onItemUpdated, onConnectionChange } = {}) {
        const stopConn = registerConnectionCallback(onConnectionChange);
        if (!echo) {
            return stopConn;
        }

        const channelName = 'inventory.live';
        const channel = echo.private(channelName);
        safeListen(channel, '.inventory.item.updated', onItemUpdated);

        return () => {
            stopConn();
            echo.leave(channelName);
        };
    },

    joinTicketPresence(ticketId, { onMembersChanged, onTyping } = {}) {
        if (!echo || !ticketId) {
            return {
                sendTyping() {},
                leave() {},
            };
        }

        const channelName = `ticket.${ticketId}.presence`;
        const channel = echo.join(channelName);
        const members = new Map();
        let lastWhisperAt = 0;

        const emitMembers = () => {
            if (typeof onMembersChanged === 'function') {
                onMembersChanged(Array.from(members.values()));
            }
        };

        channel
            .here((users) => {
                members.clear();
                (users || []).forEach((u) => {
                    if (u && u.id !== undefined) members.set(u.id, u);
                });
                emitMembers();
            })
            .joining((user) => {
                if (user && user.id !== undefined) {
                    members.set(user.id, user);
                    emitMembers();
                }
            })
            .leaving((user) => {
                if (user && user.id !== undefined) {
                    members.delete(user.id);
                    emitMembers();
                }
            })
            .listenForWhisper('typing', (payload) => {
                if (typeof onTyping === 'function' && payload) {
                    onTyping(payload);
                }
            });

        return {
            sendTyping(userName) {
                const now = Date.now();
                if (now - lastWhisperAt < 1500) {
                    return;
                }
                lastWhisperAt = now;
                try {
                    channel.whisper('typing', { name: String(userName || 'Pengguna').slice(0, 80) });
                } catch (_) {}
            },
            leave() {
                echo.leave(channelName);
            },
        };
    },

    subscribeAdminAudit({ onAuditRecorded, onConnectionChange } = {}) {
        const stopConn = registerConnectionCallback(onConnectionChange);
        if (!echo) {
            return stopConn;
        }

        const channelName = 'admin.audit';
        const channel = echo.private(channelName);
        safeListen(channel, '.audit.log.recorded', onAuditRecorded);

        return () => {
            stopConn();
            echo.leave(channelName);
        };
    },
};
