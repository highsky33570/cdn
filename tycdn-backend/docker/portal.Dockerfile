FROM node:22-alpine AS build
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npx vite build

FROM nginx:alpine
COPY --from=build /build/dist /var/www/portal/dist
