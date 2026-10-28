FROM node:22-alpine AS build

WORKDIR /app

ENV NODE_OPTIONS="--max-old-space-size=1536"

COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps --no-audit --no-fund --maxsockets=5

COPY . .
RUN npm run build \
    && npm prune --omit=dev --legacy-peer-deps

FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json ./
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/bootstrap/ssr ./bootstrap/ssr
COPY --from=build /app/public/build ./public/build

ENV NODE_ENV=production
EXPOSE 13714

CMD ["node", "bootstrap/ssr/ssr.js"]
