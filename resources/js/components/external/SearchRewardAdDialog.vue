<script setup lang="ts">
import { ref, watch } from 'vue';
import { CheckCircle2, LoaderCircle, Play } from '@lucide/vue';
import { toast } from 'vue-sonner';
import Button from '@/components/ui/Button.vue';
import { useExternalSearch } from '@/composables/useExternalSearch';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; reward: [] }>();

const external = useExternalSearch();
const viewId = ref<string | null>(null);
const seconds = ref(10);
const loading = ref(false);
const complete = ref(false);
const error = ref<string | null>(null);
let timer: ReturnType<typeof setInterval> | null = null;

const stopTimer = () => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
};

watch(
    () => props.open,
    async (open) => {
        stopTimer();
        viewId.value = null;
        seconds.value = 10;
        complete.value = false;
        error.value = null;

        if (!open) return;

        try {
            const result = await external.startReward();
            viewId.value = result.viewId;
            timer = setInterval(() => {
                seconds.value = Math.max(0, seconds.value - 1);
                if (seconds.value === 0) stopTimer();
            }, 1000);
        } catch (cause) {
            error.value = cause instanceof Error ? cause.message : 'Recompensa no disponible.';
        }
    },
);

const claim = async () => {
    if (!viewId.value || seconds.value > 0 || loading.value) return;
    loading.value = true;

    try {
        const result = await external.claimReward(viewId.value);
        complete.value = true;
        toast.success(`Token recibido. Saldo: ${result.balance}`);
        emit('reward');
        emit('close');
    } catch (cause) {
        error.value = cause instanceof Error ? cause.message : 'No pudimos entregar la recompensa.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[100] grid place-items-center bg-secondary/70 p-3 backdrop-blur-sm" @click.self="emit('close')">
            <div class="w-full max-w-md rounded-3xl bg-card p-6 shadow-elegant">
                <div class="text-center">
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">1 búsqueda gratis</p>
                    <h2 class="mt-2 text-xl font-black text-secondary">Mira el anuncio completo</h2>
                    <p class="mt-2 text-sm text-muted-foreground">Al terminar recibirás un token. Disponible una vez al día.</p>
                </div>

                <div class="mt-4 rounded-2xl bg-secondary p-6 text-center text-secondary-foreground">
                    <CheckCircle2 v-if="complete" class="mx-auto h-12 w-12 text-primary" />
                    <Play v-else class="mx-auto h-12 w-12 text-primary" />
                    <p class="mt-3 text-sm font-bold">{{ complete ? 'Recompensa entregada' : 'Anuncio de demostración' }}</p>
                    <div v-if="!complete && !error" class="mt-5 h-2 overflow-hidden rounded-full bg-secondary-foreground/15">
                        <div class="h-full bg-primary transition-all duration-1000" :style="{ width: `${((10 - seconds) / 10) * 100}%` }" />
                    </div>
                    <p v-if="!complete && !error" class="mt-2 text-xs text-secondary-foreground/70">
                        {{ viewId ? `${seconds} segundos restantes` : 'Preparando anuncio…' }}
                    </p>
                </div>

                <p v-if="error" class="mt-3 text-center text-sm text-destructive">{{ error }}</p>

                <Button variant="hero" class="mt-4 w-full gap-2" :disabled="!viewId || seconds > 0 || loading || complete || Boolean(error)" @click="claim">
                    <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin" />
                    {{ complete ? 'Token recibido' : seconds > 0 ? 'Espera para recibir tu token' : 'Recibir token' }}
                </Button>
                <Button variant="ghost" class="mt-2 w-full text-xs" @click="emit('close')">Cerrar</Button>
            </div>
        </div>
    </Teleport>
</template>
