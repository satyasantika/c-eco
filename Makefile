# Sementara: layanan C-ECO hidup di ~/code/docker-compose.yml (code_laranet).
COMPOSE := docker compose -f ../docker-compose.yml

.PHONY: up down sh test fresh build extract

up:      ; $(COMPOSE) up -d c-eco-php c-eco-nginx c-eco-worker
down:    ; $(COMPOSE) stop c-eco-php c-eco-nginx c-eco-worker
sh:      ; $(COMPOSE) exec c-eco-php bash
test:    ; $(COMPOSE) exec c-eco-php php artisan test
fresh:   ; $(COMPOSE) exec c-eco-php php artisan migrate:fresh --seed
build:   ; npm run build
extract: ; python3 tools/extract_items.py --out data
