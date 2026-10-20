<script setup lang="ts">
import { Clock, Mail, MapPin, Phone } from '@lucide/vue';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    settings?: {
        email?: string;
        phone?: string;
        whatsapp?: string;
        address?: string;
        hours?: string;
    };
    seo: { title: string; description: string; canonical: string };
}>();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    message: '',
});

const submit = () => {
    form.post('/contacto', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <section class="bg-gradient-hero px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <h1 class="page-title text-secondary">Hablemos.</h1>
            <p class="page-subtitle mt-4 max-w-xl text-muted-foreground">
                ¿Tienes dudas? Nuestro equipo te responde lo antes posible.
            </p>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2">
        <div class="space-y-5">
            <div class="flex items-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft">
                <Phone class="mt-0.5 h-5 w-5 text-primary" />
                <div><p class="font-bold text-secondary">Teléfono</p><p class="text-sm text-muted-foreground">{{ settings?.phone ?? '+52 81 1234 5678' }}</p></div>
            </div>
            <div class="flex items-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft">
                <Mail class="mt-0.5 h-5 w-5 text-primary" />
                <div><p class="font-bold text-secondary">Correo</p><p class="text-sm text-muted-foreground">{{ settings?.email ?? 'hola@wecar.mx' }}</p></div>
            </div>
            <div class="flex items-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft">
                <MapPin class="mt-0.5 h-5 w-5 text-primary" />
                <div><p class="font-bold text-secondary">Oficina Monterrey</p><p class="text-sm text-muted-foreground">{{ settings?.address ?? 'Av. Constitución 1500, Monterrey, NL' }}</p></div>
            </div>
            <div class="flex items-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft">
                <Clock class="mt-0.5 h-5 w-5 text-primary" />
                <div><p class="font-bold text-secondary">Horario</p><p class="text-sm text-muted-foreground">{{ settings?.hours ?? 'Lun a Vie · 9:00 a 18:00' }}</p></div>
            </div>
        </div>

        <form class="rounded-3xl border border-border bg-card p-6 shadow-soft sm:p-8" @submit.prevent="submit">
            <h2 class="text-xl font-black text-secondary">Envíanos un mensaje</h2>
            <div class="mt-5 space-y-4">
                <div>
                    <Input v-model="form.name" placeholder="Tu nombre" autocomplete="name" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div>
                    <Input v-model="form.email" type="email" placeholder="tu@correo.com" autocomplete="email" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-destructive">{{ form.errors.email }}</p>
                </div>
                <div>
                    <Input v-model="form.phone" type="tel" placeholder="81 1234 5678" autocomplete="tel" />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-destructive">{{ form.errors.phone }}</p>
                </div>
                <div>
                    <textarea
                        v-model="form.message"
                        rows="5"
                        placeholder="Cuéntanos en qué podemos ayudarte"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <p v-if="form.errors.message" class="mt-1 text-xs text-destructive">{{ form.errors.message }}</p>
                </div>
                <Button type="submit" class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Enviando…' : 'Enviar mensaje' }}
                </Button>
            </div>
        </form>
    </section>
</template>
