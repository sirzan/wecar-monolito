<script setup lang="ts">
import SeoHead from '@/components/site/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{ settings?: { title?: string; effective_date?: string; content?: string }; seo: { title: string; description: string; canonical: string } }>();
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        <h1 class="text-3xl font-black text-secondary sm:text-4xl">
            {{ settings?.title || 'Aviso de Privacidad — WeCar' }}
        </h1>
        <p class="mt-2 text-sm text-muted-foreground">
            <strong>Versión 2.0</strong> — Efectiva desde: {{ settings?.effective_date || '01/01/2025' }}
        </p>

        <div v-if="settings?.content" class="prose prose-sm mt-10 max-w-none text-secondary/80" v-html="settings.content" />

        <div v-else class="mt-10 space-y-4 text-sm leading-relaxed text-secondary/80">
            <h2 class="mt-8 text-lg font-black text-secondary">1. Información que Recopilamos</h2>
            <p>En WeCar, recopilamos información personal que usted nos proporciona directamente cuando:</p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Se registra en nuestra plataforma.</li>
                <li>Utiliza nuestros servicios de compra, venta o financiamiento de vehículos.</li>
                <li>Se comunica con nuestro equipo de atención al cliente.</li>
                <li>Participa en encuestas, promociones o programas de sostenibilidad.</li>
                <li>Utiliza nuestras aplicaciones móviles y herramientas digitales.</li>
                <li>Interactúa con nuestras cuentas en redes sociales.</li>
            </ul>

            <h3 class="mt-6 text-base font-black text-secondary">1.1 Información Automática</h3>
            <p>También recopilamos automáticamente cierta información cuando utiliza nuestros servicios:</p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Información del dispositivo (tipo, sistema operativo, identificadores únicos).</li>
                <li>Datos de ubicación geográfica (cuando esté habilitado).</li>
                <li>Información de uso y navegación dentro de la plataforma.</li>
                <li>Direcciones IP y datos de conexión a la red.</li>
                <li>Cookies y tecnologías de rastreo similares.</li>
            </ul>

            <h2 class="mt-8 text-lg font-black text-secondary">2. Uso de la Información</h2>
            <p>Utilizamos su información personal para las siguientes finalidades:</p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Procesar sus reservas, transacciones y solicitudes de servicios.</li>
                <li>Proporcionar servicios de atención al cliente y soporte técnico.</li>
                <li>Mejorar nuestros servicios, plataforma y experiencia del usuario.</li>
                <li>Enviar comunicaciones importantes sobre su cuenta y transacciones.</li>
                <li>Cumplir con obligaciones legales, regulatorias y fiscales.</li>
                <li>Personalizar su experiencia y recomendar contenidos relevantes.</li>
                <li>Prevenir fraudes, suplantación de identidad y garantizar la seguridad.</li>
                <li>Realizar análisis estadísticos, estudios de mercado y desarrollo de productos.</li>
                <li>Implementar y mejorar nuestros sistemas de inteligencia artificial.</li>
            </ul>

            <h2 class="mt-8 text-lg font-black text-secondary">3. Compartir Información con Terceros</h2>
            <p>
                No vendemos, alquilamos ni comercializamos su información personal con terceros. Podemos compartir sus
                datos únicamente en las siguientes circunstancias:
            </p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Con su consentimiento explícito para fines específicos.</li>
                <li>Para cumplir con la ley, procesos legales o requerimientos de autoridades competentes.</li>
                <li>Para proteger nuestros derechos, propiedad o seguridad, así como los de nuestros usuarios.</li>
                <li>
                    Con proveedores de servicios que nos ayudan a operar nuestro negocio (pago, verificación de
                    identidad, análisis de datos), bajo estrictos acuerdos de confidencialidad.
                </li>
                <li>En caso de fusión, adquisición o venta de activos, notificando previamente a los usuarios afectados.</li>
                <li>Con instituciones financieras asociadas para procesar solicitudes de crédito y financiamiento.</li>
            </ul>

            <h2 class="mt-8 text-lg font-black text-secondary">4. Cookies y Tecnologías Similares</h2>
            <p>Utilizamos cookies propias y de terceros para:</p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Mejorar su experiencia de navegación y funcionalidad de la plataforma.</li>
                <li>Analizar el uso y rendimiento de nuestros servicios.</li>
                <li>Personalizar el contenido y los anuncios que usted ve.</li>
                <li>Recordar sus preferencias y configuraciones.</li>
                <li>Facilitar el inicio de sesión y autenticación segura.</li>
            </ul>
            <p>
                Puede gestionar sus preferencias de cookies en la configuración de su navegador o a través de nuestro
                centro de preferencias de privacidad. La desactivación de ciertas cookies puede afectar la funcionalidad
                de la plataforma.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">5. Transferencias Internacionales</h2>
            <p>
                Sus datos personales pueden ser transferidos y procesados en países fuera de su jurisdicción, incluyendo
                aquellos que puedan no tener un nivel de protección de datos equivalente al de su país. Garantizamos que
                todas las transferencias internacionales cumplen con las leyes de protección de datos aplicables y que
                sus datos reciben un nivel adecuado de protección mediante cláusulas contractuales tipo y demás
                mecanismos legales requeridos.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">6. Retención de Datos</h2>
            <p>
                Conservamos su información personal únicamente durante el tiempo necesario para cumplir con los
                propósitos descritos en esta política, a menos que la ley requiera o permita un período de retención más
                largo. Una vez cumplidos estos fines, sus datos serán eliminados de manera segura o anonimizados para
                fines estadísticos.
            </p>
            <p>
                Los plazos de retención específicos varían según el tipo de dato y la finalidad, pero en ningún caso
                excederán los 10 años posteriores a su última interacción con la plataforma, salvo obligación legal en
                contrario.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">7. Seguridad de Datos</h2>
            <p>
                Implementamos medidas de seguridad técnicas, organizativas y administrativas apropiadas para proteger su
                información personal, incluyendo:
            </p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Cifrado de datos en tránsito (TLS 1.3) y en reposo (AES-256).</li>
                <li>Controles de acceso estrictos basados en roles y autenticación multifactor.</li>
                <li>Monitoreo continuo de seguridad y detección de intrusiones.</li>
                <li>Capacitación regular del personal en protección de datos.</li>
                <li>Evaluaciones periódicas de vulnerabilidades y pruebas de penetración.</li>
                <li>Procedimientos de respuesta a incidentes y notificación de brechas.</li>
            </ul>

            <h2 class="mt-8 text-lg font-black text-secondary">8. Sus Derechos</h2>
            <p>De acuerdo con la legislación aplicable, usted tiene los siguientes derechos sobre sus datos personales:</p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li><strong>Acceso:</strong> conocer qué datos personales tenemos y cómo los utilizamos.</li>
                <li><strong>Rectificación:</strong> solicitar la corrección de información inexacta o incompleta.</li>
                <li><strong>Cancelación:</strong> solicitar la eliminación de sus datos cuando ya no sean necesarios.</li>
                <li><strong>Oposición:</strong> oponerse al tratamiento de sus datos para fines específicos.</li>
                <li>
                    <strong>Portabilidad:</strong> recibir sus datos en formato estructurado y transferirlos a otro
                    responsable.
                </li>
                <li><strong>Revocación del consentimiento:</strong> retirar su consentimiento en cualquier momento.</li>
                <li><strong>Limitación del tratamiento:</strong> solicitar que se limite el uso de sus datos.</li>
                <li><strong>Presentar una queja:</strong> ante la autoridad de protección de datos correspondiente.</li>
            </ul>
            <p>
                Para ejercer cualquiera de estos derechos, puede contactarnos a través de los medios indicados en la
                sección de contacto. Responderemos a su solicitud en un plazo máximo de 20 días hábiles.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">9. Menores de Edad</h2>
            <p>
                Nuestros servicios no están dirigidos a menores de 18 años. No recopilamos intencionalmente información
                personal de menores sin el consentimiento parental verificable. Si tenemos conocimiento de que hemos
                recopilado datos de un menor sin el consentimiento adecuado, procederemos a eliminarlos de manera
                inmediata.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">10. Cambios a esta Política</h2>
            <p>
                Podemos actualizar esta política de privacidad periódicamente para reflejar cambios en nuestras
                prácticas, tecnologías o requisitos legales. Le notificaremos sobre cambios significativos a través de
                los canales de comunicación habituales (correo electrónico, notificaciones en plataforma) con al menos
                15 días de anticipación.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">11. Contacto y Oficial de Protección de Datos</h2>
            <p>
                Si tiene preguntas sobre esta política de privacidad, desea ejercer sus derechos o reportar alguna
                irregularidad, puede contactarnos a través de los siguientes medios:
            </p>
            <div class="rounded-2xl border border-border bg-muted/40 p-5">
                <p class="font-black text-secondary">Wecar Technology S.A.S.</p>
                <p class="mt-2">
                    Correo:
                    <a href="mailto:privacidad@wecar.mx" class="font-semibold text-primary hover:underline">
                        privacidad@wecar.mx
                    </a>
                </p>
                <p>
                    Teléfono:
                    <a href="tel:+528112345678" class="font-semibold text-primary hover:underline">+52 81 1234 5678</a>
                </p>
                <p>Dirección: Washington 1400, Col. Centro, Monterrey, Nuevo León, México.</p>
                <p class="mt-3 font-bold text-secondary">Oficial de Protección de Datos</p>
                <p>
                    Correo:
                    <a href="mailto:dpo@wecar.mx" class="font-semibold text-primary hover:underline">dpo@wecar.mx</a>
                </p>
            </div>
        </div>
    </section>
</template>
