FROM node:22-bookworm-slim@sha256:c3de60bf2f9dd0ac6370e6117950ff62d6e339527e7472301c9c78a017978392
WORKDIR /app
RUN chown node:node /app
COPY --chown=node:node frontend/package.json frontend/package-lock.json ./
USER node
RUN npm ci && sha256sum package-lock.json | cut -d' ' -f1 > node_modules/.rrhh-lock
COPY --chown=node:node frontend/ ./
COPY --chmod=755 docker/frontend/entrypoint.sh /usr/local/bin/rrhh-frontend
ENTRYPOINT ["rrhh-frontend"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173", "--strictPort"]
