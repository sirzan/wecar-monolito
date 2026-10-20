import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

function urlBase64ToUint8Array(base64String: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const bytes = new Uint8Array(rawData.length);

    for (let index = 0; index < rawData.length; index++) {
        bytes[index] = rawData.charCodeAt(index);
    }

    return bytes;
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function usePushNotifications() {
    const page = usePage<{ push: { publicKey: string | null } }>();
    const supported = ref(
        typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window,
    );
    const loading = ref(false);
    const subscribed = ref(false);
    const publicKey = page.props.push?.publicKey ?? '';

    const sync = async () => {
        if (!supported.value) return;
        const registration = await navigator.serviceWorker.getRegistration('/push-sw.js');
        const subscription = await registration?.pushManager.getSubscription();
        subscribed.value = Boolean(subscription);
    };

    const subscribe = async () => {
        if (!supported.value) {
            toast.error('Este navegador no admite notificaciones push.');
            return;
        }

        if (!publicKey) {
            toast.error('Las notificaciones push aún no están configuradas.');
            return;
        }

        loading.value = true;

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                toast.error('Permite las notificaciones para continuar.');
                return;
            }

            const registration = await navigator.serviceWorker.register('/push-sw.js', {
                scope: '/',
            });
            await navigator.serviceWorker.ready;

            const existing = await registration.pushManager.getSubscription();
            const subscription =
                existing ??
                (await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                }));

            const response = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(subscription.toJSON()),
            });

            if (!response.ok) throw new Error('No se pudo guardar la suscripción.');

            subscribed.value = true;
            toast.success('Notificaciones activadas.');
        } catch {
            toast.error('No se pudieron activar las notificaciones.');
        } finally {
            loading.value = false;
        }
    };

    const unsubscribe = async () => {
        loading.value = true;

        try {
            const registration = await navigator.serviceWorker.getRegistration('/push-sw.js');
            const subscription = await registration?.pushManager.getSubscription();

            if (subscription) {
                await fetch('/api/push/unsubscribe', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });

                await subscription.unsubscribe();
            }

            subscribed.value = false;
            toast.success('Notificaciones desactivadas.');
        } catch {
            toast.error('No se pudieron desactivar las notificaciones.');
        } finally {
            loading.value = false;
        }
    };

    void sync();

    return { supported, loading, subscribed, subscribe, unsubscribe };
}
