<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Globe } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    agency: {
        id: string;
        slug: string;
        name: string;
        description: string | null;
        city: string | null;
        state: string | null;
        address: string | null;
        phone: string | null;
        whatsapp: string | null;
        hours: string | null;
        legal_name: string | null;
        rfc: string | null;
        plan: string;
        max_cars: number;
    };
}>();

const base = `/panel/agencia/${props.agency.slug}`;

const form = useForm({
    name: props.agency.name,
    description: props.agency.description ?? '',
    city: props.agency.city ?? '',
    state: props.agency.state ?? '',
    address: props.agency.address ?? '',
    phone: props.agency.phone ?? '',
    whatsapp: props.agency.whatsapp ?? '',
    hours: props.agency.hours ?? '',
    legal_name: props.agency.legal_name ?? '',
    rfc: props.agency.rfc ?? '',
});

const submit = () => form.post(`${base}/perfil`, { preserveScroll: true });
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Perfil público</h1>
        <p class="mt-1 text-sm text-muted-foreground">Estos datos se muestran en tu página de agencia.</p>

        <a :href="`/agencia/${agency.slug}`" target="_blank" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
            <Globe class="h-4 w-4" /> Ver perfil público
        </a>

        <form class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <Label for="name">Nombre de la agencia</Label>
                    <Input id="name" v-model="form.name" class="mt-1.5" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="sm:col-span-2">
                    <Label for="description">Descripción</Label>
                    <textarea id="description" v-model="form.description" rows="3" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                </div>
                <div>
                    <Label for="city">Ciudad</Label>
                    <Input id="city" v-model="form.city" class="mt-1.5" />
                </div>
                <div>
                    <Label for="state">Estado</Label>
                    <Input id="state" v-model="form.state" class="mt-1.5" />
                </div>
                <div class="sm:col-span-2">
                    <Label for="address">Dirección</Label>
                    <Input id="address" v-model="form.address" class="mt-1.5" />
                </div>
                <div>
                    <Label for="phone">Teléfono</Label>
                    <Input id="phone" v-model="form.phone" class="mt-1.5" />
                </div>
                <div>
                    <Label for="whatsapp">WhatsApp</Label>
                    <Input id="whatsapp" v-model="form.whatsapp" class="mt-1.5" />
                </div>
                <div>
                    <Label for="hours">Horario</Label>
                    <Input id="hours" v-model="form.hours" class="mt-1.5" />
                </div>
                <div>
                    <Label for="legal_name">Razón social</Label>
                    <Input id="legal_name" v-model="form.legal_name" class="mt-1.5" />
                </div>
                <div>
                    <Label for="rfc">RFC</Label>
                    <Input id="rfc" v-model="form.rfc" class="mt-1.5" />
                </div>
            </div>
            <Button type="submit" class="mt-6" :disabled="form.processing">Guardar perfil</Button>
        </form>
    </div>
</template>
