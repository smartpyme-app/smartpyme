# syntax=docker/dockerfile:1.7
# Frontend SmartPyme (Angular 20). Compila y sirve los archivos estáticos con nginx,
# con las mismas reglas de caché que el .htaccess de app.smartpyme.site.
#
# NG_CONFIGURATION:
#   production         -> apunta a la API de producción (lo que hoy se sube al servidor)
#   production,docker  -> optimizado igual, pero contra la API local (http://localhost:8080)

FROM node:22.21.1-bookworm-slim AS build
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY . .
ARG NG_CONFIGURATION=production
RUN npm run ng -- build --configuration ${NG_CONFIGURATION}

FROM nginx:1.28.0-alpine AS web
COPY --from=docker frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/Frontend /usr/share/nginx/html
# Los source maps no se publican (se suben solo a Sentry)
RUN find /usr/share/nginx/html -name '*.map' -delete
EXPOSE 80
