<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

defineOptions({ layout: AdminLayout });

interface AdminUser {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    roles: string[];
    created_at: string | null;
}

const props = defineProps<{
    users: { data: AdminUser[]; current_page: number; last_page: number; total: number };
    filters: Record<string, string>;
    roles: string[];
}>();

const page = usePage<{ auth: { user: { id: string; roles: string[] } } }>();
const canManage = computed(() => page.props.auth.user.roles.includes('superadmin'));

const state = reactive<Record<string, { roles: string[]; is_active: boolean }>>(
    Object.fromEntries(props.users.data.map((user) => [user.id, { roles: [...user.roles], is_active: user.is_active }])),
);

const q = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (q.value) params.q = q.value;
    if (role.value) params.role = role.value;
    router.get('/admin/usuarios', params, { preserveState: true, preserveScroll: true, replace: true });
};

const toggleRole = (userId: string, roleName: string) => {
    const current = state[userId].roles;
    state[userId].roles = current.includes(roleName)
        ? current.filter((item) => item !== roleName)
        : [...current, roleName];
};

const save = (user: AdminUser) => {
    router.patch(`/admin/usuarios/${user.id}`, state[user.id], { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Usuarios</h1>
        <p class="mt-1 text-sm text-muted-foreground">{{ users.total }} usuarios registrados.</p>

        <div class="mt-6 grid gap-3 rounded-2xl border border-border bg-card/50 p-4 md:grid-cols-[1fr_180px_auto]">
            <Input v-model="q" placeholder="Buscar por nombre o correo…" @keyup.enter="applyFilters" />
            <select v-model="role" class="h-11 rounded-xl border border-input bg-background px-3 text-sm" @change="applyFilters">
                <option value="">Todos los roles</option>
                <option v-for="item in roles" :key="item" :value="item">{{ item }}</option>
            </select>
            <Button @click="applyFilters">Filtrar</Button>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[800px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="p-4">Usuario</th>
                        <th class="p-4">Roles admin</th>
                        <th class="p-4">Estado</th>
                        <th class="p-4">Registro</th>
                        <th class="p-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="p-4">
                            <p class="font-medium">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                            <p v-if="user.phone" class="text-xs text-muted-foreground">{{ user.phone }}</p>
                        </td>
                        <td class="p-4">
                            <div class="flex flex-wrap gap-2">
                                <label v-for="roleName in roles" :key="roleName" class="inline-flex items-center gap-1.5 rounded-full border border-border px-2 py-1 text-[11px]">
                                    <input
                                        type="checkbox"
                                        :checked="state[user.id].roles.includes(roleName)"
                                        :disabled="!canManage || user.id === page.props.auth.user.id"
                                        @change="toggleRole(user.id, roleName)"
                                    />
                                    {{ roleName }}
                                </label>
                                <span v-if="!state[user.id].roles.length" class="text-xs text-muted-foreground">Sin rol admin</span>
                            </div>
                        </td>
                        <td class="p-4">
                            <label class="inline-flex items-center gap-2 text-xs">
                                <input v-model="state[user.id].is_active" type="checkbox" :disabled="!canManage || user.id === page.props.auth.user.id" />
                                {{ state[user.id].is_active ? 'Activo' : 'Inactivo' }}
                            </label>
                        </td>
                        <td class="p-4 text-xs text-muted-foreground">
                            {{ user.created_at ? new Date(user.created_at).toLocaleDateString('es-MX') : '—' }}
                        </td>
                        <td class="p-4 text-right">
                            <Button v-if="canManage && user.id !== page.props.auth.user.id" size="sm" @click="save(user)">Guardar</Button>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="5" class="p-8 text-center text-muted-foreground">Sin resultados.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :current-page="users.current_page" :last-page="users.last_page" base-path="/admin/usuarios" :base-query="filters" />
    </div>
</template>
