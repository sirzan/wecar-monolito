<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{ email: string; token: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () =>
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <SeoHead title="Nueva contraseña — Wecar" description="Restablece tu contraseña de Wecar." />

    <section class="mx-auto max-w-md px-4 py-10 sm:px-6 sm:py-16">
        <div class="rounded-3xl border border-border bg-card p-6 shadow-elegant sm:p-8">
            <div class="flex justify-center">
                <Logo />
            </div>

            <h1 class="mt-5 text-center text-xl font-semibold tracking-tight text-secondary">Nueva contraseña</h1>
            <p class="mt-2 text-center text-sm text-muted-foreground">Elige una contraseña segura para tu cuenta.</p>

            <form class="mt-6 grid gap-4" @submit.prevent="submit">
                <div>
                    <Label for="email">Correo electrónico</Label>
                    <Input id="email" v-model="form.email" type="email" class="mt-1.5" autocomplete="email" />
                    <p v-if="form.errors.email" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.email }}</p>
                </div>

                <div>
                    <Label for="password">Contraseña</Label>
                    <div class="relative mt-1.5">
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="pr-11"
                            autocomplete="new-password"
                            placeholder="••••••••"
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
                    <p v-if="form.errors.password" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.password }}</p>
                </div>

                <div>
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPassword ? 'text' : 'password'"
                        class="mt-1.5"
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                </div>

                <Button type="submit" variant="hero" size="xl" class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : 'Guardar contraseña' }}
                </Button>
            </form>
        </div>
    </section>
</template>
