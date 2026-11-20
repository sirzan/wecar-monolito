import { computed, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const deferred = ref<BeforeInstallPromptEvent | null>(null);
const installed = ref(false);
let bound = false;

function bind() {
    if (bound || typeof window === 'undefined') {
        return;
    }

    bound = true;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred.value = event as BeforeInstallPromptEvent;
    });

    window.addEventListener('appinstalled', () => {
        installed.value = true;
        deferred.value = null;
    });
}

export function useInstallPrompt() {
    bind();

    const canInstall = computed(() => deferred.value !== null && !installed.value);

    const install = async (): Promise<boolean> => {
        if (!deferred.value) {
            return false;
        }

        await deferred.value.prompt();
        const choice = await deferred.value.userChoice;
        deferred.value = null;

        if (choice.outcome === 'accepted') {
            installed.value = true;
        }

        return choice.outcome === 'accepted';
    };

    return { canInstall, installed, install };
}
