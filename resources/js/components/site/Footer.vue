<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { HelpCircle, Mail, Music2, Phone } from '@lucide/vue';
import Logo from '@/components/site/Logo.vue';

const page = usePage<{ site: { contact: Record<string, string>; footer: Record<string, string> } }>();
const contact = computed(() => page.props.site?.contact ?? {});
const footer = computed(() => page.props.site?.footer ?? {});
</script>

<template>
    <footer class="bg-secondary text-secondary-foreground">
        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-9 sm:gap-8 sm:px-6 sm:py-11 md:grid-cols-2 md:gap-10 md:py-14 lg:grid-cols-4">
            <div>
                <Logo variant="light" />
                <p class="mt-4 text-sm text-secondary-foreground/70">
                    Encuentra tu auto ideal entre más de 500,000 opciones que buscamos para ti.
                </p>
                <div class="mt-5 flex gap-3">
                    <a href="#" aria-label="Facebook" class="grid h-9 w-9 place-items-center rounded-full bg-white/10 transition-colors hover:bg-primary">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                            <path d="M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.9.3-1.5 1.6-1.5h1.4V4.9c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8V11H8v3h2.6v7h2.9z" />
                        </svg>
                    </a>
                    <a href="#" aria-label="Instagram" class="grid h-9 w-9 place-items-center rounded-full bg-white/10 transition-colors hover:bg-primary">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="5" />
                            <circle cx="12" cy="12" r="4" />
                            <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                        </svg>
                    </a>
                    <a href="#" aria-label="TikTok" class="grid h-9 w-9 place-items-center rounded-full bg-white/10 transition-colors hover:bg-primary">
                        <Music2 class="h-4 w-4" />
                    </a>
                    <a
                        href="https://wa.me/5218112345678"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="WhatsApp"
                        class="grid h-9 w-9 place-items-center rounded-full bg-white/10 transition-colors hover:bg-primary"
                    >
                        <svg viewBox="0 0 32 32" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                            <path d="M19.11 17.205c-.372 0-1.088 1.39-1.518 1.39a.63.63 0 01-.315-.1c-.802-.402-1.504-.817-2.163-1.447-.545-.516-1.146-1.29-1.46-1.963a.426.426 0 01-.073-.215c0-.33.99-.945.99-1.49 0-.143-.73-2.09-.832-2.335-.143-.372-.214-.487-.6-.487-.187 0-.36-.043-.53-.043-.302 0-.53.115-.746.315-.688.645-1.032 1.318-1.06 2.264v.114c-.015.99.472 1.977 1.017 2.78 1.23 1.82 2.506 3.41 4.554 4.34.616.287 2.035.888 2.722.888.817 0 2.15-.515 2.478-1.318.215-.515.215-.946.143-1.06-.058-.157-.244-.215-.515-.357-.272-.144-1.461-.717-1.677-.79zM16.001 28.685a12.39 12.39 0 01-6.15-1.62L5 28.366l1.32-4.778A12.366 12.366 0 013.685 16C3.685 9.21 9.213 3.685 16 3.685c3.296 0 6.394 1.282 8.72 3.611A12.31 12.31 0 0128.31 16c-.002 6.79-5.527 12.685-12.31 12.685zm0-22.95C10.34 5.735 5.735 10.34 5.735 16c0 1.94.546 3.842 1.578 5.49l.245.387-.785 2.86 2.954-.773.378.224a10.235 10.235 0 005.21 1.428c5.66 0 10.265-4.605 10.265-10.265a10.197 10.197 0 00-2.998-7.265 10.196 10.196 0 00-7.27-3.013z" />
                        </svg>
                    </a>
                </div>
            </div>

            <div>
                <h4 class="text-sm font-bold text-primary">Explora</h4>
                <ul class="mt-4 space-y-2 text-sm text-secondary-foreground/80">
                    <li><Link href="/comprar" class="hover:text-primary">Comprar auto</Link></li>
                    <li><Link href="/vende" class="hover:text-primary">Vende tu auto</Link></li>
                    <li><Link href="/credito" class="hover:text-primary">Solicitar crédito</Link></li>
                    <li><Link href="/garantia" class="hover:text-primary">Garantía</Link></li>
                    <li><Link href="/certificaciones" class="hover:text-primary">Certificaciones</Link></li>
                    <li>
                        <a href="https://motorevs.com.mx/" target="_blank" rel="noopener noreferrer" class="hover:text-primary">
                            Cuida tu auto
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h4 class="text-sm font-bold text-primary">Empresa</h4>
                <ul class="mt-4 space-y-2 text-sm text-secondary-foreground/80">
                    <li><Link href="/contacto" class="hover:text-primary">Contacto</Link></li>
                    <li><Link href="/garantia#faq" class="hover:text-primary">FAQ´s</Link></li>
                    <li><Link href="/nosotros" class="hover:text-primary">Nosotros</Link></li>
                </ul>
            </div>

            <div>
                <h4 class="text-sm font-bold text-primary">Contacto</h4>
                <ul class="mt-4 space-y-3 text-sm text-secondary-foreground/80">
                    <li>
                        <Link href="/contacto" class="flex items-start gap-2 hover:text-primary">
                            <HelpCircle class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span>
                                <strong class="text-secondary-foreground">Ayuda</strong><br />
                                Centro de atención al cliente
                            </span>
                        </Link>
                    </li>
                    <li>
                        <a :href="`tel:${contact.phone || '+528138241038'}`" class="flex items-start gap-2 hover:text-primary">
                            <Phone class="mt-0.5 h-4 w-4 text-primary" /> {{ contact.phone || '+52 81 38241038' }}
                        </a>
                    </li>
                    <li>
                        <a :href="`mailto:${contact.email || 'info@wecar.mx'}`" class="flex items-start gap-2 hover:text-primary">
                            <Mail class="mt-0.5 h-4 w-4 text-primary" /> {{ contact.email || 'info@wecar.mx' }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-white/10">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-6 py-4 text-center text-xs text-secondary-foreground/60 sm:flex-row sm:text-left">
                <span>{{ footer.copyright || `© ${new Date().getFullYear()} Wecar México S.A.S. Todos los derechos reservados.` }}</span>
                <div class="flex items-center gap-4">
                    <Link href="/terminos" class="hover:text-primary">Términos y condiciones</Link>
                    <Link href="/privacidad" class="hover:text-primary">Aviso de privacidad</Link>
                </div>
            </div>
        </div>
    </footer>
</template>
