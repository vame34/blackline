import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Avoid deprecated unload listeners in browsers: use pagehide lifecycle instead.
if (window.Pusher?.Runtime) {
    window.Pusher.Runtime.addUnloadListener = (callback) => {
        window.addEventListener('pagehide', callback, { passive: true });
    };

    window.Pusher.Runtime.removeUnloadListener = (callback) => {
        window.removeEventListener('pagehide', callback);
    };
}

const reverbConfig = window.BlacklineReverb ?? {};
const reverbKey = reverbConfig.key;
const reverbHost = reverbConfig.host ?? window.location.hostname;
const reverbPort = Number(reverbConfig.port ?? 8081);
const reverbScheme = reverbConfig.scheme ?? 'http';
const realtimeEnabled = window.BlacklineRealtimeEnabled === true;

if (realtimeEnabled && reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else if (!window.Echo) {
    const noopChannel = {
        listen: () => noopChannel,
        stopListening: () => noopChannel,
        notification: () => noopChannel,
        here: () => noopChannel,
        joining: () => noopChannel,
        leaving: () => noopChannel,
    };

    // Livewire checks window.Echo existence for echo listeners.
    // Provide a no-op implementation when realtime is disabled.
    window.Echo = {
        socketId: () => null,
        channel: () => noopChannel,
        private: () => noopChannel,
        encryptedPrivate: () => noopChannel,
        join: () => noopChannel,
        leaveChannel: () => {},
    };
}
