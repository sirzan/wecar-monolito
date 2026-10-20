<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PanelLayout });

const form = useForm({
    name: '',
    description: '',
    city: '',
    state: '',
    phone: '',
    whatsapp: '',
});

const submit = () => form.post('/panel/agencia/crear');
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent text-accent-foreground"><Building2 class="h-6 w-6" /></span>
        <h1 class="mt-4 text-2xl font-black text-secondary sm:text-3xl">Crea tu agencia</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            Publica inventario, recibe leads y administra a tu equipo.
        </p>

        <form class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <Label for="name">Nombre de la agencia</Label>
                    <Input id="name" v-model="form.name" class="mt-1.5" placeholder="Mi agencia" />
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
                <div>
                    <Label for="phone">Teléfono</Label>
                    <Input id="phone" v-model="form.phone" class="mt-1.5" />
                </div>
                <div>
                    <Label for="whatsapp">WhatsApp</Label>
                    <Input id="whatsapp" v-model="form.whatsapp" class="mt-1.5" />
                </div>
            </div>
            <Button type="submit" class="mt-6" :disabled="form.processing">Crear agencia</Button>
        </form>
    </div>
</template>
