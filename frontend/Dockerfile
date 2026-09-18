# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# build: compiles the Vue SPA to static files. VITE_* vars are baked into
# the JS bundle at build time (Vite has no runtime env support), so they're
# build ARGs here, not container environment — passed via
# `docker compose build --build-arg VITE_API_URL=...` or the `args:` block
# in docker-compose.yml.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .

ARG VITE_API_URL=https://my.urtg.uz/api/v1
ENV VITE_API_URL=$VITE_API_URL

RUN npm run build

# ---------------------------------------------------------------------------
# nginx: serves the compiled dist/ as a single-page app (unknown paths fall
# back to index.html so Vue Router's history mode works on a hard refresh).
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/default.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
