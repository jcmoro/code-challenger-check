# syntax=docker/dockerfile:1.7
FROM node:20-alpine

RUN apk add --no-cache bash git

WORKDIR /app

# Vite dev server
EXPOSE 5173

CMD ["sh", "-c", "if [ -f package.json ]; then npm run dev -- --host 0.0.0.0; else echo 'No package.json yet — run make bootstrap'; sleep infinity; fi"]
