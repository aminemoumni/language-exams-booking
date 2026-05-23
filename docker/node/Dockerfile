# ─── Base stage ───────────────────────────────────────────────────────────────
FROM node:20-alpine AS base

WORKDIR /app

# ─── Dev stage ────────────────────────────────────────────────────────────────
FROM base AS dev

COPY package.json package-lock.json* ./
# npm install (pas npm ci) car package-lock.json est généré au premier build
RUN npm install

COPY . .

EXPOSE 3000
CMD ["npm", "run", "dev"]

# ─── Builder stage ────────────────────────────────────────────────────────────
FROM base AS builder

COPY package.json package-lock.json* ./
# npm ci en prod pour builds déterministes (nécessite package-lock.json)
RUN npm install

COPY . .

RUN npm run build

# ─── Prod stage ───────────────────────────────────────────────────────────────
FROM base AS prod

ENV NODE_ENV=production

RUN addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 nextjs

COPY --from=builder /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs

EXPOSE 3000
ENV PORT 3000
ENV HOSTNAME "0.0.0.0"

CMD ["node", "server.js"]
