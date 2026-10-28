FROM node:22-alpine AS build

WORKDIR /app

ENV NODE_OPTIONS="--max-old-space-size=1536 --dns-result-order=ipv4first --no-network-family-autoselection"

COPY package.json package-lock.json ./
RUN npm install --legacy-peer-deps --no-audit --no-fund --maxsockets=5

COPY . .
RUN npm run build \
    && npm prune --omit=dev --legacy-peer-deps

FROM node:22-alpine

WORKDIR /app

ENV NODE_ENV=production
ENV NODE_OPTIONS="--dns-result-order=ipv4first --no-network-family-autoselection"

COPY package.json package-lock.json ./
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/bootstrap/ssr ./bootstrap/ssr
COPY --from=build /app/public/build ./public/build

EXPOSE 13714

CMD ["node", "bootstrap/ssr/ssr.js"]
