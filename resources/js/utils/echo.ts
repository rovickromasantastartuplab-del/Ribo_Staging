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

    echoInstance = new Echo({
        broadcaster: 'pusher',
        key: key,
        cluster: cluster,
        forceTLS: true,
    });

    return echoInstance;
};

export default getEcho;
