<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, MailCheck } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PublicLayout });

const form = useForm({ email: '' });
const submit = () => form.post('/forgot-password');
</script>

<template>
    <SeoHead title="Recuperar contraseña — Wecar" description="Recupera el acceso a tu cuenta Wecar." />

    <section class="mx-auto max-w-md px-4 py-10 sm:px-6 sm:py-16">
        <div class="rounded-3xl border border-border bg-card p-6 shadow-elegant sm:p-8">
            <div class="flex justify-center">
                <Logo />
            </div>

            <template v-if="form.recentlySuccessful">
                <div class="mt-6 text-center">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <MailCheck class="h-8 w-8" />
                    </div>
                    <h1 class="mt-4 text-xl font-semibold text-secondary">Revisa tu correo</h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Enviamos un enlace para restablecer tu contraseña a
                        <strong class="text-foreground">{{ form.email }}</strong>.
                    </p>
                    <Button as="a" href="/login" class="mt-6 w-full">
                        <ArrowLeft class="h-4 w-4" /> Volver a iniciar sesión
                    </Button>
                </div>
            </template>

            <template v-else>
                <h1 class="mt-5 text-center text-xl font-semibold tracking-tight text-secondary">Recupera tu contraseña</h1>
                <p class="mt-2 text-center text-sm text-muted-foreground">
                    Te enviaremos un enlace para restablecer tu contraseña.
                </p>

                <form class="mt-6 grid gap-4" @submit.prevent="submit">
                    <div>
                        <Label for="email">Correo electrónico</Label>
                        <Input id="email" v-model="form.email" type="email" class="mt-1.5" autocomplete="email" placeholder="tu@correo.com" />
                        <p v-if="form.errors.email" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.email }}</p>
                    </div>
                    <Button type="submit" variant="hero" size="xl" class="w-full" :disabled="form.processing">
                        {{ form.processing ? 'Enviando…' : 'Enviar enlace' }}
                    </Button>
                </form>

                <p class="mt-6 text-center text-sm text-muted-foreground">
                    <Link href="/login" class="font-semibold text-primary hover:underline">Volver a iniciar sesión</Link>
                </p>
            </template>
        </div>
    </section>
</template>
