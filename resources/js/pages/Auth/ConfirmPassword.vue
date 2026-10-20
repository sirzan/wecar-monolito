<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PublicLayout });

const form = useForm({ password: '' });
const submit = () => form.post('/user/confirm-password');
</script>

<template>
    <SeoHead title="Confirma tu contraseña — Wecar" description="Confirma tu contraseña para continuar." />

    <section class="mx-auto max-w-md px-4 py-10 sm:px-6 sm:py-16">
        <div class="rounded-3xl border border-border bg-card p-6 shadow-elegant sm:p-8">
            <div class="flex justify-center">
                <Logo />
            </div>

            <h1 class="mt-5 text-center text-xl font-semibold tracking-tight text-secondary">Confirma tu contraseña</h1>
            <p class="mt-2 text-center text-sm text-muted-foreground">
                Por seguridad, confirma tu contraseña para continuar.
            </p>

            <form class="mt-6 grid gap-4" @submit.prevent="submit">
                <div>
                    <Label for="password">Contraseña</Label>
                    <Input id="password" v-model="form.password" type="password" class="mt-1.5" autocomplete="current-password" />
                    <p v-if="form.errors.password" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.password }}</p>
                </div>
                <Button type="submit" variant="hero" size="xl" class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Confirmando…' : 'Confirmar' }}
                </Button>
            </form>
        </div>
    </section>
</template>
