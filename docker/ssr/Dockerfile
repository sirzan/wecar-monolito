FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps --no-audit --no-fund

COPY . .
RUN npm run build:ssr \
    && npm prune --omit=dev --legacy-peer-deps

FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json ./
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/bootstrap/ssr ./bootstrap/ssr

ENV NODE_ENV=production
EXPOSE 13714

CMD ["node", "bootstrap/ssr/ssr.js"]
