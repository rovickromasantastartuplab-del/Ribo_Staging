import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance: Echo<any> | null = null;

export const getEcho = () => {
    if (echoInstance) return echoInstance;

    if (typeof window !== 'undefined') {
        (window as any).Pusher = Pusher;
    }

    const key = (window as any).appSettings?.get('pusher_app_key') || import.meta.env.VITE_PUSHER_APP_KEY;
    const cluster = (window as any).appSettings?.get('pusher_app_cluster') || import.meta.env.VITE_PUSHER_APP_CLUSTER;

    // Gracefully handle missing Pusher App Key
    if (!key || key.trim() === '') {
        console.warn('Pusher App Key is not set in your .env (VITE_PUSHER_APP_KEY). Real-time functionality is disabled.');
        
        // Return a mock Echo instance so chained methods like .private().listen() do not throw undefined errors
        const dummyChannel = { listen: () => dummyChannel, stopListening: () => dummyChannel };
        const mockEcho = {
            private: () => dummyChannel,
            channel: () => dummyChannel,
            join: () => dummyChannel,
            leave: () => {},
            leaveChannel: () => {},
        } as any;
        
        return mockEcho;
    }

    // Get the CSRF token for private channel authentication
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    echoInstance = new Echo({
        broadcaster: 'pusher',
        key: key,
        cluster: cluster,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
            },
        },
    });

    return echoInstance;
};

export default getEcho;
