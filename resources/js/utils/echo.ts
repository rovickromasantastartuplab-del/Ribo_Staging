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
