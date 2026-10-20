<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PublicLayout });

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

const submit = () => form.post('/two-factor-challenge', { preserveScroll: true });
</script>

<template>
    <SeoHead title="Verificación en dos pasos — Wecar" description="Confirma tu código de seguridad." />

    <section class="mx-auto max-w-md px-4 py-10 sm:px-6 sm:py-16">
        <div class="rounded-3xl border border-border bg-card p-6 shadow-elegant sm:p-8">
            <div class="flex justify-center">
                <Logo />
            </div>

            <h1 class="mt-5 text-center text-xl font-semibold tracking-tight text-secondary">Verificación en dos pasos</h1>
            <p class="mt-2 text-center text-sm text-muted-foreground">
                Ingresa el código de tu autenticador o usa un código de recuperación.
            </p>

            <form class="mt-6 grid gap-4" @submit.prevent="submit">
                <div v-if="!useRecovery">
                    <Label for="code">Código de autenticación</Label>
                    <Input id="code" v-model="form.code" inputmode="numeric" autocomplete="one-time-code" class="mt-1.5" placeholder="123456" />
                    <p v-if="form.errors.code" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.code }}</p>
                </div>
                <div v-else>
                    <Label for="recovery_code">Código de recuperación</Label>
                    <Input id="recovery_code" v-model="form.recovery_code" class="mt-1.5" placeholder="xxxxx-xxxxx" />
                    <p v-if="form.errors.recovery_code" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.recovery_code }}</p>
                </div>

                <Button type="submit" variant="hero" size="xl" class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Verificando…' : 'Verificar' }}
                </Button>
            </form>

            <button
                type="button"
                class="mt-4 w-full text-sm font-semibold text-primary hover:underline"
                @click="useRecovery = !useRecovery"
            >
                {{ useRecovery ? 'Usar código de autenticación' : 'Usar código de recuperación' }}
            </button>
        </div>
    </section>
</template>
