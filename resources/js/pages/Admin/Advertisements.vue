<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: AdminLayout });

interface AdItem {
    id: string;
    slug: string;
    title: string;
    description: string | null;
    image_url: string | null;
    cta_label: string | null;
    cta_url: string | null;
    placement: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    impressions: number;
    clicks: number;
    sort_order: number;
}

const props = defineProps<{
    advertisements: AdItem[];
    filters: { status?: string };
    placements: string[];
    statuses: string[];
}>();

const editingId = ref<string | null>(null);
const form = reactive({
    slug: '',
    title: '',
    description: '',
    image_url: '',
    cta_label: '',
    cta_url: '',
    placement: 'home_inline',
    status: 'draft',
    starts_at: '',
    ends_at: '',
    sort_order: 0,
});

const reset = () => {
    editingId.value = null;
    Object.assign(form, { slug: '', title: '', description: '', image_url: '', cta_label: '', cta_url: '', placement: 'home_inline', status: 'draft', starts_at: '', ends_at: '', sort_order: 0 });
};

const edit = (item: AdItem) => {
    editingId.value = item.id;
    Object.assign(form, {
        slug: item.slug,
        title: item.title,
        description: item.description ?? '',
        image_url: item.image_url ?? '',
        cta_label: item.cta_label ?? '',
        cta_url: item.cta_url ?? '',
        placement: item.placement,
        status: item.status,
        starts_at: item.starts_at ? item.starts_at.slice(0, 16) : '',
        ends_at: item.ends_at ? item.ends_at.slice(0, 16) : '',
        sort_order: item.sort_order,
    });
};

const submit = () => {
    if (editingId.value) {
        router.patch(`/admin/publicidad/${editingId.value}`, form, { preserveScroll: true, onSuccess: reset });
    } else {
        router.post('/admin/publicidad', form, { preserveScroll: true, onSuccess: reset });
    }
};

const destroy = (item: AdItem) => {
    if (!window.confirm(`¿Eliminar ${item.title}?`)) return;
    router.delete(`/admin/publicidad/${item.id}`, { preserveScroll: true });
};

const filterStatus = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    router.get('/admin/publicidad', value ? { status: value } : {}, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Publicidad</h1>
                <p class="mt-1 text-sm text-muted-foreground">Anuncios y espacios publicitarios del sitio.</p>
            </div>
            <select class="h-10 rounded-xl border border-input bg-background px-3 text-sm" :value="filters.status ?? ''" @change="filterStatus">
                <option value="">Todos</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
            <form class="rounded-2xl border border-border bg-card/50 p-5" @submit.prevent="submit">
                <h2 class="font-semibold">{{ editingId ? 'Editar anuncio' : 'Nuevo anuncio' }}</h2>
                <div class="mt-4 space-y-3">
                    <div><Label>Slug</Label><Input v-model="form.slug" class="mt-1.5" placeholder="promo-verano" /></div>
                    <div><Label>Título</Label><Input v-model="form.title" class="mt-1.5" /></div>
                    <div><Label>Descripción</Label><textarea v-model="form.description" rows="2" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" /></div>
                    <div><Label>Imagen (URL)</Label><Input v-model="form.image_url" class="mt-1.5" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><Label>CTA</Label><Input v-model="form.cta_label" class="mt-1.5" /></div>
                        <div><Label>URL CTA</Label><Input v-model="form.cta_url" class="mt-1.5" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Ubicación</Label>
                            <select v-model="form.placement" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                <option v-for="placement in placements" :key="placement" :value="placement">{{ placement }}</option>
                            </select>
                        </div>
                        <div>
                            <Label>Estado</Label>
                            <select v-model="form.status" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><Label>Inicio</Label><Input v-model="form.starts_at" type="datetime-local" class="mt-1.5" /></div>
                        <div><Label>Fin</Label><Input v-model="form.ends_at" type="datetime-local" class="mt-1.5" /></div>
                    </div>
                    <div><Label>Orden</Label><Input v-model.number="form.sort_order" type="number" min="0" class="mt-1.5" /></div>
                </div>
                <div class="mt-5 flex gap-2">
                    <Button type="submit">{{ editingId ? 'Guardar' : 'Crear' }}</Button>
                    <Button v-if="editingId" type="button" variant="ghost" @click="reset">Cancelar</Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-2xl border border-border bg-card/50">
                <table class="w-full min-w-[800px] text-sm">
                    <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr><th class="p-4">Anuncio</th><th class="p-4">Ubicación</th><th class="p-4">Estado</th><th class="p-4">Impr./Clicks</th><th class="p-4 text-right">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in advertisements" :key="item.id">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <img v-if="item.image_url" :src="item.image_url" :alt="item.title" class="h-10 w-16 rounded object-cover" />
                                    <div>
                                        <p class="font-medium">{{ item.title }}</p>
                                        <p class="text-xs text-muted-foreground">{{ item.slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-muted-foreground">{{ item.placement }}</td>
                            <td class="p-4"><span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase text-primary">{{ item.status }}</span></td>
                            <td class="p-4 text-muted-foreground">{{ item.impressions }} / {{ item.clicks }}</td>
                            <td class="p-4">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="sm" @click="edit(item)"><Pencil class="h-4 w-4" /></Button>
                                    <Button variant="ghost" size="sm" class="text-destructive" @click="destroy(item)"><Trash2 class="h-4 w-4" /></Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!advertisements.length"><td colspan="5" class="p-8 text-center text-muted-foreground">Sin anuncios.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
