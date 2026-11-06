<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2, ShieldCheck } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { usePushNotifications } from '@/composables/usePush';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    profile: {
        name: string;
        email: string;
        phone: string | null;
        city: string | null;
        state: string | null;
        birthdate: string | null;
        rfc: string | null;
        curp: string | null;
        two_factor_enabled: boolean;
        is_agency: boolean;
    };
}>();

const form = useForm({
    name: props.profile.name,
    phone: props.profile.phone ?? '',
    city: props.profile.city ?? '',
    state: props.profile.state ?? '',
    birthdate: props.profile.birthdate ?? '',
    rfc: props.profile.rfc ?? '',
    curp: props.profile.curp ?? '',
});

const submit = () => form.post('/panel/configuracion', { preserveScroll: true });

const accountType = useForm({ account_type: props.profile.is_agency ? 'agency' : 'personal' });
const saveAccountType = () => accountType.patch('/panel/tipo-cuenta', { preserveScroll: true });

const push = usePushNotifications();

const twoFactorEnabled = ref(props.profile.two_factor_enabled);
const twoFactorStep = ref<'idle' | 'password' | 'setup'>('idle');
const twoFactorPassword = ref('');
const twoFactorCode = ref('');
const twoFactorQr = ref('');
const twoFactorRecoveryCodes = ref<string[]>([]);
const twoFactorLoading = ref(false);
const twoFactorError = ref('');

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const request = async (url: string, method = 'POST', body?: Record<string, unknown>) => {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const payload = await response.json().catch(() => ({}));

    return { response, payload };
};

const loadTwoFactorSetup = async () => {
    const [qr, codes] = await Promise.all([
        fetch('/user/two-factor-qr-code', { headers: { Accept: 'application/json' } }).then((response) => response.json().catch(() => ({}))),
        fetch('/user/two-factor-recovery-codes', { headers: { Accept: 'application/json' } }).then((response) => response.json().catch(() => [])),
    ]);

    twoFactorQr.value = qr.svg ?? '';
    twoFactorRecoveryCodes.value = Array.isArray(codes) ? codes : [];
    twoFactorStep.value = 'setup';
};

const startTwoFactor = async () => {
    twoFactorLoading.value = true;
    twoFactorError.value = '';

    try {
        const { response } = await request('/user/two-factor-authentication');

        if (response.status === 423) {
            twoFactorStep.value = 'password';
            return;
        }

        if (!response.ok) {
            twoFactorError.value = 'No se pudo iniciar la activación. Intenta de nuevo.';
            return;
        }

        await loadTwoFactorSetup();
    } finally {
        twoFactorLoading.value = false;
    }
};

const confirmPassword = async () => {
    twoFactorLoading.value = true;
    twoFactorError.value = '';

    try {
        const { response } = await request('/user/confirm-password', 'POST', { password: twoFactorPassword.value });

        if (!response.ok) {
            twoFactorError.value = 'La contraseña no es correcta.';
            return;
        }

        twoFactorPassword.value = '';
        await startTwoFactor();
    } finally {
        twoFactorLoading.value = false;
    }
};

const confirmTwoFactor = async () => {
    twoFactorLoading.value = true;
    twoFactorError.value = '';

    try {
        const { response } = await request('/user/confirmed-two-factor-authentication', 'POST', { code: twoFactorCode.value });

        if (!response.ok) {
            twoFactorError.value = 'El código no es válido. Verifica tu aplicación autenticadora.';
            return;
        }

        twoFactorEnabled.value = true;
        twoFactorStep.value = 'idle';
        twoFactorCode.value = '';
    } finally {
        twoFactorLoading.value = false;
    }
};

const disableTwoFactor = async () => {
    if (!window.confirm('¿Desactivar la verificación en dos pasos?')) {
        return;
    }

    twoFactorLoading.value = true;
    twoFactorError.value = '';

    try {
        const { response } = await request('/user/two-factor-authentication', 'DELETE');

        if (!response.ok) {
            twoFactorError.value = 'No se pudo desactivar. Confirma tu contraseña e intenta de nuevo.';
            return;
        }

        twoFactorEnabled.value = false;
        twoFactorStep.value = 'idle';
        twoFactorQr.value = '';
        twoFactorRecoveryCodes.value = [];
    } finally {
        twoFactorLoading.value = false;
    }
};
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Configuración</h1>
        <p class="mt-1 text-sm text-muted-foreground">Actualiza tus datos personales y de contacto.</p>

        <form class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <Label for="name">Nombre completo</Label>
                    <Input id="name" v-model="form.name" class="mt-1.5" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div>
                    <Label for="email">Correo</Label>
                    <Input id="email" :model-value="profile.email" disabled class="mt-1.5" />
                </div>
                <div>
                    <Label for="phone">Teléfono</Label>
                    <Input id="phone" v-model="form.phone" class="mt-1.5" />
                </div>
                <div>
                    <Label for="birthdate">Fecha de nacimiento</Label>
                    <Input id="birthdate" v-model="form.birthdate" type="date" class="mt-1.5" />
                </div>
                <div>
                    <Label for="city">Ciudad</Label>
                    <Input id="city" v-model="form.city" class="mt-1.5" />
                </div>
                <div>
                    <Label for="state">Estado</Label>
                    <Input id="state" v-model="form.state" class="mt-1.5" />
                </div>
                <div>
                    <Label for="rfc">RFC</Label>
                    <Input id="rfc" v-model="form.rfc" class="mt-1.5" />
                </div>
                <div>
                    <Label for="curp">CURP</Label>
                    <Input id="curp" v-model="form.curp" class="mt-1.5" />
                </div>
            </div>
            <Button type="submit" class="mt-6" :disabled="form.processing">Guardar cambios</Button>
        </form>

        <!-- Tipo de cuenta -->
        <div class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft">
            <h2 class="font-bold text-secondary">Tipo de cuenta</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Tu tipo de perfil define las secciones disponibles en el panel.
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    class="rounded-2xl border p-4 text-left transition"
                    :class="accountType.account_type === 'personal' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                    @click="accountType.account_type = 'personal'"
                >
                    <p class="text-sm font-bold text-secondary">Personal</p>
                    <p class="mt-1 text-xs text-muted-foreground">Publica y vende tus autos como particular.</p>
                </button>
                <button
                    type="button"
                    class="rounded-2xl border p-4 text-left transition"
                    :class="accountType.account_type === 'agency' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                    @click="accountType.account_type = 'agency'"
                >
                    <p class="text-sm font-bold text-secondary">Agencia</p>
                    <p class="mt-1 text-xs text-muted-foreground">Inventario, leads y equipo para lotes o concesionarias.</p>
                </button>
            </div>

            <p v-if="accountType.errors.account_type" class="mt-2 text-xs text-destructive">{{ accountType.errors.account_type }}</p>
            <Button class="mt-4" variant="outline" :disabled="accountType.processing" @click="saveAccountType">
                Guardar tipo de cuenta
            </Button>
        </div>

        <!-- Seguridad -->
        <div class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex items-start gap-3">
                <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-success" />
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-secondary">Seguridad</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ twoFactorEnabled ? 'La verificación en dos pasos está activa.' : 'Activa la verificación en dos pasos para proteger tu cuenta.' }}
                    </p>

                    <div class="mt-4">
                        <Button v-if="!twoFactorEnabled && twoFactorStep === 'idle'" variant="outline" size="sm" :disabled="twoFactorLoading" @click="startTwoFactor">
                            Activar verificación en dos pasos
                        </Button>

                        <div v-else-if="twoFactorStep === 'password'" class="max-w-sm space-y-3">
                            <p class="text-xs text-muted-foreground">Confirma tu contraseña para continuar.</p>
                            <Input v-model="twoFactorPassword" type="password" placeholder="Contraseña actual" />
                            <Button size="sm" :disabled="twoFactorLoading" @click="confirmPassword">Confirmar</Button>
                        </div>

                        <div v-else-if="twoFactorStep === 'setup'" class="space-y-4">
                            <p class="text-sm text-muted-foreground">
                                Escanea el código con tu aplicación autenticadora y guarda tus códigos de recuperación.
                            </p>
                            <div v-if="twoFactorQr" class="w-fit rounded-xl bg-white p-3" v-html="twoFactorQr" />
                            <div v-if="twoFactorRecoveryCodes.length" class="rounded-xl bg-muted/50 p-4">
                                <p class="text-xs font-bold text-secondary">Códigos de recuperación</p>
                                <div class="mt-2 grid grid-cols-2 gap-1 font-mono text-xs text-muted-foreground">
                                    <span v-for="code in twoFactorRecoveryCodes" :key="code">{{ code }}</span>
                                </div>
                            </div>
                            <div class="max-w-xs space-y-2">
                                <Label for="two_factor_code">Código de verificación</Label>
                                <Input id="two_factor_code" v-model="twoFactorCode" inputmode="numeric" placeholder="123456" />
                                <Button size="sm" :disabled="twoFactorLoading" @click="confirmTwoFactor">Confirmar y activar</Button>
                            </div>
                        </div>

                        <Button v-else-if="twoFactorEnabled" variant="destructive" size="sm" :disabled="twoFactorLoading" @click="disableTwoFactor">
                            Desactivar verificación en dos pasos
                        </Button>

                        <p v-if="twoFactorError" class="mt-2 text-xs font-semibold text-destructive">{{ twoFactorError }}</p>
                    </div>

                    <Button
                        v-if="push.supported.value"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        :disabled="push.loading.value"
                        @click="push.subscribed.value ? push.unsubscribe() : push.subscribe()"
                    >
                        {{ push.subscribed.value ? 'Desactivar notificaciones push' : 'Activar notificaciones push' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
