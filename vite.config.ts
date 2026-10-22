import { defineConfig, loadEnv, type UserConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

const localhostOrigin = /^https?:\/\/(?:(?:[^:]+\.)?localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/;

export default defineConfig(({ mode }): UserConfig => {
    const env = loadEnv(mode, process.cwd(), 'VITE_');

    /*
     * Origen público del dev server. Necesario cuando la app se abre desde otro
     * dispositivo de la red local: el archivo public/hot y las URLs de los
     * assets deben apuntar a la IP/host alcanzable por el navegador, no a
     * "localhost".
     *
     * Ejemplo en .env: VITE_DEV_SERVER_ORIGIN=http://192.168.100.172:5173
     */
    const devServerOrigin = env.VITE_DEV_SERVER_ORIGIN?.trim();
    const devServerUrl = devServerOrigin ? new URL(devServerOrigin) : null;

    const extraAllowedOrigins = (env.VITE_DEV_ALLOWED_ORIGINS ?? '')
        .split(',')
        .map((origin) => origin.trim())
        .filter(Boolean);

    const allowedOrigins: (string | RegExp)[] = [localhostOrigin, ...extraAllowedOrigins];

    if (devServerUrl) {
        // Permite la app servida desde el mismo host del dev server en
        // cualquier puerto (p. ej. http://192.168.100.172:8000).
        const escapedHost = devServerUrl.hostname.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        allowedOrigins.push(new RegExp(`^https?://${escapedHost}(:\\d+)?$`));
    }

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                ssr: 'resources/js/ssr.ts',
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            },
        },
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            ...(devServerOrigin ? { origin: devServerOrigin } : {}),
            hmr: {
                host: devServerUrl?.hostname ?? 'localhost',
                ...(devServerUrl?.port ? { clientPort: Number(devServerUrl.port) } : {}),
            },
            cors: {
                origin: allowedOrigins,
            },
            ...(devServerUrl ? { allowedHosts: [devServerUrl.hostname] } : {}),
            watch: {
                usePolling: env.VITE_USE_POLLING === 'true',
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
