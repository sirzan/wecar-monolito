<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { LogOut, MailCheck, RefreshCw } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PublicLayout });

const page = usePage<{ auth: { user: { name: string; email: string } | null } }>();
const form = useForm({});
const emailForm = useForm({
    email: page.props.auth.user?.email ?? '',
    current_password: '',
});
const resend = () => form.post('/email/verification-notification');
const changeEmail = () =>
    emailForm.put('/email/verification-email', {
        preserveScroll: true,
        onSuccess: () => emailForm.reset('current_password'),
    });
</script>

<template>
    <SeoHead title="Verifica tu correo — Wecar" description="Confirma tu correo para activar tu cuenta Wecar." />

    <section class="mx-auto max-w-md px-4 py-10 sm:px-6 sm:py-16">
        <div class="rounded-3xl border border-border bg-card p-6 text-center shadow-elegant sm:p-8">
            <div class="flex justify-center">
                <Logo />
            </div>

            <div class="mx-auto mt-6 grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-primary">
                <MailCheck class="h-8 w-8" />
            </div>
            <h1 class="mt-4 text-xl font-semibold text-secondary">Revisa tu correo</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Enviamos un enlace de verificación a
                <strong class="text-foreground">{{ page.props.auth.user?.email }}</strong>.
                Si no lo ves, revisa spam o reenvíalo.
            </p>

            <Button class="mt-6 w-full" :disabled="form.processing" @click="resend">
                <RefreshCw class="h-4 w-4" :class="form.processing ? 'animate-spin' : ''" />
                {{ form.processing ? 'Reenviando…' : 'Reenviar correo' }}
            </Button>

            <p v-if="form.recentlySuccessful" class="mt-3 text-xs font-semibold text-success">
                Correo reenviado correctamente.
            </p>

            <div class="mt-8 border-t border-border pt-6 text-left">
                <h2 class="text-sm font-semibold text-secondary">¿Es incorrecto el correo?</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Corrígelo y enviaremos un nuevo enlace de verificación. Necesitas tu contraseña actual para proteger la cuenta.
                </p>

                <form class="mt-4 grid gap-4" @submit.prevent="changeEmail">
                    <div>
                        <Label for="verification-email">Nuevo correo</Label>
                        <Input
                            id="verification-email"
                            v-model="emailForm.email"
                            type="email"
                            autocomplete="email"
                            required
                            class="mt-1.5"
                        />
                        <p v-if="emailForm.errors.email" class="mt-1 text-xs text-destructive">
                            {{ emailForm.errors.email }}
                        </p>
                    </div>

                    <div>
                        <Label for="verification-password">Contraseña actual</Label>
                        <Input
                            id="verification-password"
                            v-model="emailForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-1.5"
                        />
                        <p v-if="emailForm.errors.current_password" class="mt-1 text-xs text-destructive">
                            {{ emailForm.errors.current_password }}
                        </p>
                    </div>

                    <Button type="submit" variant="outline" :disabled="emailForm.processing">
                        {{ emailForm.processing ? 'Actualizando…' : 'Actualizar correo' }}
                    </Button>
                </form>
            </div>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground"
            >
                <LogOut class="h-4 w-4" />
                Cerrar sesión
            </Link>
        </div>
    </section>
</template>
