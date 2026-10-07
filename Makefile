.DEFAULT_GOAL := help

COMPOSE = docker compose -f docker/docker-compose.yml

.PHONY: help config build up down restart logs ps composer-install test

help:
	@printf '%s\n' \
	  'Commandes disponibles :' \
	  '  make config           Valider la configuration Docker Compose' \
	  '  make build            Construire les images' \
	  '  make up               Construire et lancer les conteneurs' \
	  '  make down             Arrêter les conteneurs' \
	  '  make restart          Redémarrer les conteneurs' \
	  '  make logs             Afficher les journaux' \
	  '  make ps               Afficher l’état des conteneurs' \
	  '  make composer-install Installer les dépendances PHP' \
	  '  make test             Lancer les tests PHPUnit' \
	  '  Export SQL gzip       Automatique chaque nuit à minuit (fuseau TZ)'

config:
	$(COMPOSE) config

build:
	$(COMPOSE) build

up:
	$(COMPOSE) up --build -d

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

composer-install:
	HOST_UID=$$(id -u) HOST_GID=$$(id -g) $(COMPOSE) run --rm composer install --no-interaction

test:
	$(COMPOSE) run --rm --no-deps app vendor/bin/phpunit --configuration phpunit.xml