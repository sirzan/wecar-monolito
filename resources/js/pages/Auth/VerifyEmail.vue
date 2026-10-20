<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { MailCheck, RefreshCw } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PublicLayout });

const page = usePage<{ auth: { user: { email: string } | null } }>();
const form = useForm({});
const resend = () => form.post('/email/verification-notification');
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
        </div>
    </section>
</template>
