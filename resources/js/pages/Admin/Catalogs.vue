<script setup lang="ts">
import { reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { cn } from '@/lib/utils';

defineOptions({ layout: AdminLayout });

interface CatalogItem {
    id: string;
    name: string;
    slug?: string;
    logo?: string | null;
    sort_order?: number;
    brand_id?: string;
    brand_name?: string;
    model_id?: string;
    model_name?: string;
    year?: string | null;
    label?: string;
    models_count?: number;
}

const props = defineProps<{
    resource: 'brands' | 'models' | 'versions' | 'statuses';
    items: CatalogItem[];
    options: {
        brands: { id: string; name: string }[];
        models: { id: string; brand_id: string; name: string }[];
    };
}>();

const tabs = [
    { key: 'brands', label: 'Marcas' },
    { key: 'models', label: 'Modelos' },
    { key: 'versions', label: 'Versiones' },
    { key: 'statuses', label: 'Estatus' },
];

const editingId = ref<string | null>(null);

const form = reactive({
    name: '',
    slug: '',
    logo: '',
    sort_order: 0,
    brand_id: '',
    model_id: '',
    year: '',
    label: '',
});

const reset = () => {
    editingId.value = null;
    Object.assign(form, { name: '', slug: '', logo: '', sort_order: 0, brand_id: '', model_id: '', year: '', label: '' });
};

const edit = (item: CatalogItem) => {
    editingId.value = item.id;
    Object.assign(form, {
        name: item.name,
        slug: item.slug ?? '',
        logo: item.logo ?? '',
        sort_order: item.sort_order ?? 0,
        brand_id: item.brand_id ?? '',
        model_id: item.model_id ?? '',
        year: item.year ?? '',
        label: item.label ?? '',
    });
};

const submit = () => {
    if (editingId.value) {
        router.patch(`/admin/catalogos/${props.resource}/${editingId.value}`, form, { preserveScroll: true, onSuccess: reset });
    } else {
        router.post(`/admin/catalogos/${props.resource}`, form, { preserveScroll: true, onSuccess: reset });
    }
};

const destroy = (item: CatalogItem) => {
    if (!window.confirm(`¿Eliminar ${item.name}?`)) return;
    router.delete(`/admin/catalogos/${props.resource}/${item.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Catálogos</h1>
        <p class="mt-1 text-sm text-muted-foreground">Marcas, modelos, versiones y estatus del catálogo.</p>

        <div class="mt-5 flex flex-wrap gap-2">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="`/admin/catalogos/${tab.key}`"
                :class="cn('rounded-full px-4 py-2 text-sm font-semibold', resource === tab.key ? 'bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:text-foreground')"
            >
                {{ tab.label }}
            </Link>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
            <form class="rounded-2xl border border-border bg-card/50 p-5" @submit.prevent="submit">
                <h2 class="font-semibold">{{ editingId ? 'Editar' : 'Nuevo' }} registro</h2>

                <div class="mt-4 space-y-3">
                    <div v-if="resource === 'models'">
                        <Label>Marca</Label>
                        <select v-model="form.brand_id" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                            <option value="">Selecciona</option>
                            <option v-for="brand in options.brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                        </select>
                    </div>

                    <div v-if="resource === 'versions'">
                        <Label>Modelo</Label>
                        <select v-model="form.model_id" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                            <option value="">Selecciona</option>
                            <option v-for="model in options.models" :key="model.id" :value="model.id">{{ model.name }}</option>
                        </select>
                    </div>

                    <div>
                        <Label for="name">{{ resource === 'statuses' ? 'Nombre interno' : 'Nombre' }}</Label>
                        <Input id="name" v-model="form.name" class="mt-1.5" />
                    </div>

                    <div v-if="resource === 'statuses'">
                        <Label for="label">Etiqueta visible</Label>
                        <Input id="label" v-model="form.label" class="mt-1.5" />
                    </div>

                    <div v-if="resource === 'brands' || resource === 'models'">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" class="mt-1.5" placeholder="Se genera automáticamente" />
                    </div>

                    <div v-if="resource === 'brands'">
                        <Label for="logo">Logo (URL)</Label>
                        <Input id="logo" v-model="form.logo" class="mt-1.5" />
                    </div>

                    <div v-if="resource === 'versions'">
                        <Label for="year">Año</Label>
                        <Input id="year" v-model="form.year" class="mt-1.5" />
                    </div>

                    <div v-if="resource !== 'versions'">
                        <Label for="sort_order">Orden</Label>
                        <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" class="mt-1.5" />
                    </div>
                </div>

                <div class="mt-5 flex gap-2">
                    <Button type="submit">{{ editingId ? 'Guardar' : 'Crear' }}</Button>
                    <Button v-if="editingId" type="button" variant="ghost" @click="reset">Cancelar</Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-2xl border border-border bg-card/50">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="p-4">Nombre</th>
                            <th v-if="resource === 'models'" class="p-4">Marca</th>
                            <th v-if="resource === 'versions'" class="p-4">Modelo</th>
                            <th v-if="resource === 'versions'" class="p-4">Año</th>
                            <th v-if="resource === 'brands'" class="p-4">Modelos</th>
                            <th v-if="resource === 'statuses'" class="p-4">Etiqueta</th>
                            <th class="p-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in items" :key="item.id">
                            <td class="p-4 font-medium">{{ item.name }}</td>
                            <td v-if="resource === 'models'" class="p-4 text-muted-foreground">{{ item.brand_name }}</td>
                            <td v-if="resource === 'versions'" class="p-4 text-muted-foreground">{{ item.brand_name }} {{ item.model_name }}</td>
                            <td v-if="resource === 'versions'" class="p-4 text-muted-foreground">{{ item.year ?? '—' }}</td>
                            <td v-if="resource === 'brands'" class="p-4 text-muted-foreground">{{ item.models_count }}</td>
                            <td v-if="resource === 'statuses'" class="p-4 text-muted-foreground">{{ item.label }}</td>
                            <td class="p-4">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="sm" @click="edit(item)"><Pencil class="h-4 w-4" /></Button>
                                    <Button variant="ghost" size="sm" class="text-destructive" @click="destroy(item)"><Trash2 class="h-4 w-4" /></Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="7" class="p-8 text-center text-muted-foreground">Sin registros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
            <Plus class="h-4 w-4" /> Los cambios se aplican de inmediato al catálogo público.
        </div>
    </div>
</template>
