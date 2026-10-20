FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps

COPY . .
RUN npm run build

FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --omit=dev --legacy-peer-deps

COPY --from=build /app/bootstrap/ssr ./bootstrap/ssr

ENV NODE_ENV=production
EXPOSE 13714

CMD ["node", "bootstrap/ssr/ssr.js"]
