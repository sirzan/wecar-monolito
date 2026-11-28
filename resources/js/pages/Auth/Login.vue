<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import FormErrorSummary from '@/components/ui/FormErrorSummary.vue';
import FormFieldError from '@/components/ui/FormFieldError.vue';
import { useRequiredValidation } from '@/composables/useRequiredValidation';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    redirect?: string | null;
}>();

const redirectQuery = computed(() =>
    props.redirect ? `?redirect=${encodeURIComponent(props.redirect)}` : '',
);
const googleHref = computed(() => `/auth/google/redirect${redirectQuery.value}`);
const registerHref = computed(() => `/registro${redirectQuery.value}`);

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);
const validation = useRequiredValidation(form, {
    email: 'correo electrónico',
    password: 'contraseña',
});

const submit = () => {
    if (!validation.validate()) return;

    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <SeoHead title="Iniciar sesión — Wecar" description="Inicia sesión en tu cuenta Wecar." />

    <section class="mx-auto max-w-md px-4 py-10 sm:px-6 sm:py-16">
        <div class="rounded-3xl border border-border bg-card p-6 shadow-elegant sm:p-8">
            <div class="flex justify-center">
                <Logo />
            </div>

            <h1 class="mt-5 text-center text-xl font-semibold tracking-tight text-secondary">Iniciar sesión</h1>
            <p class="mt-2 text-center text-sm text-muted-foreground">Gestiona tus autos, trámites y pagos.</p>

            <a
                :href="googleHref"
                class="mt-6 flex h-12 w-full items-center justify-center gap-3 rounded-full border border-input bg-background text-sm font-semibold transition-colors hover:bg-accent"
            >
                <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.3-.4-3.5z" />
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
                    <path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.2 35 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.3 5.7l6.3 5.3C41.4 35.4 44 30.1 44 24c0-1.3-.1-2.3-.4-3.5z" />
                </svg>
                Continuar con Google
            </a>

            <div class="my-6 flex items-center gap-3 text-xs text-muted-foreground">
                <span class="h-px flex-1 bg-border" /> o con email
                <span class="h-px flex-1 bg-border" />
            </div>

            <form class="grid gap-4" @submit.prevent="submit">
                <FormErrorSummary :fields="validation.summary.value" />
                <div>
                    <Label for="email">Correo electrónico</Label>
                    <Input id="email" v-model="form.email" type="email" :invalid="validation.hasError('email')" class="mt-1.5" autocomplete="email" placeholder="tu@correo.com" @input="validation.clearField('email')" />
                    <FormFieldError :message="validation.fieldError('email')" />
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <Label for="password">Contraseña</Label>
                        <Link href="/forgot-password" class="text-xs font-semibold text-primary hover:underline">
                            ¿Olvidaste tu contraseña?
                        </Link>
                    </div>
                    <div class="relative mt-1.5">
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            :invalid="validation.hasError('password')"
                            class="pr-11"
                            autocomplete="current-password"
                            @input="validation.clearField('password')"
                        />
                        <button
                            type="button"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground hover:text-foreground"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <FormFieldError :message="validation.fieldError('password')" />
                </div>

                <label class="flex items-center gap-2 text-sm text-muted-foreground">
                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-input" />
                    Recordarme
                </label>

                <Button type="submit" variant="hero" size="xl" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Entrando…' : 'Entrar' }}
                </Button>
            </form>

            <p class="mt-6 text-center text-sm text-muted-foreground">
                ¿Aún no tienes cuenta?
                <Link :href="registerHref" class="font-bold text-primary hover:underline">Regístrate</Link>
            </p>
        </div>
    </section>
</template>
