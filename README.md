# Scal-e CDP Backend

Ce projet est un backend PHP 8.2+ pour une plateforme de données clients (CDP) simplifiée, conçue dans le cadre du test technique Scal-e. L’architecture est pensée pour démontrer une maîtrise de la POO, des services, repositories et de la modélisation MySQL sans framework.

## Arborescence du projet

```text
.
├── .env
├── .env.example
├── .gitignore
├── README.md
├── Makefile
├── composer.json
├── command/
│   └── export-database.php
├── docker/
│   ├── cron/
│   │   ├── Dockerfile
│   │   └── entrypoint.sh
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── apache/
│       └── default.conf
├── phpunit.xml
├── public/
│   ├── .htaccess
│   ├── assets/
│   │   ├── app.js
│   │   └── styles.css
│   └── index.php
├── sql/
│   ├── migrations/
│   │   └── 20261007_event_types.sql
│   ├── schema.sql
│   └── seed.sql
├── src/
│   ├── Config/
│   │   └── Database.php
│   ├── Controllers/
│   │   ├── BaseController.php
│   │   ├── CustomerController.php
│   │   ├── EventController.php
│   │   └── SegmentController.php
│   ├── Http/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Router.php
│   ├── Repositories/
│   │   ├── CustomerRepository.php
│   │   ├── EventRepository.php
│   │   └── SegmentRepository.php
│   ├── Services/
│   │   ├── CustomerService.php
│   │   ├── EventService.php
│   │   └── SegmentationService.php
│   └── Validation/
│       └── Validator.php
├── templates/
│   └── dashboard.html
├── tests/
│   └── ApiTest.php
└── prompt-init.md
```

## Stack technique

- PHP 8.2
- Apache HTTP Server
- MySQL 8.0
- PDO natif
- Composer PSR-4
- PHPUnit

## Installation locale sans Docker

1. Installer PHP 8.2+, Composer et MySQL 8.
2. Créer une base `scal_e_cdp`.
3. Copier `.env.example` vers `.env` et adapter les valeurs.
4. Exécuter le schéma SQL :

```bash
mysql -u root -p < sql/schema.sql
mysql -u root -p < sql/seed.sql
```

5. Installer les dépendances :

```bash
composer install
```

6. Lancer le serveur PHP avec Apache localement ou utiliser le serveur interne PHP :

```bash
php -S 0.0.0.0:8000 -t public
```

## Installation avec Docker

```bash
make composer-install
make up
```

Ensuite :

- application accessible sur http://localhost:8080
- phpMyAdmin accessible sur http://localhost:8081 ; utiliser `DB_USERNAME` et `DB_PASSWORD` de `.env`
- MySQL accessible sur localhost:3306
- base de données : `scal_e_cdp`

Les commandes disponibles sont listées avec `make help`. Pour valider directement la configuration Compose :

```bash
make config
```

Les routes `/api/` exigent un header `Authorization: Bearer <clé>`. La ou les clés sont définies dans `API_KEYS` du fichier `.env` ; plusieurs clés peuvent être séparées par des virgules. Pour générer une clé aléatoire : `openssl rand -hex 32`. Les appels du dashboard utilisent `/dashboard-api/`, qui reste public pour conserver son interactivité.

Le service `cron` exporte automatiquement la base chaque nuit à minuit. Le fuseau horaire est défini par `TZ` dans `.env` et vaut `Africa/Nairobi` par défaut. Les archives compressées sont enregistrées dans `exports/` avec le format `<nom_bdd>_YYYYMMDDHHMMSS.sql.gz`. Ce dossier est ignoré par Git. L’export n’est pas lancé manuellement depuis le Makefile.

## Points d’entrée API

### 1) Ingestion d’événement

Endpoint : `POST /api/events`

Exemple de payload :

```json
{
  "customer": {
    "email": "john@example.com",
    "name": "John Doe"
  },
  "event": "purchase",
  "properties": {
    "amount": 120,
    "product": "Shoes"
  },
  "timestamp": "2026-04-10T12:00:00Z"
}
```

Exemple cURL :

```bash
curl -X POST http://localhost:8080/api/events \
  -H "Content-Type: application/json" \
  -d '{
    "customer": {"email":"john@example.com","name":"John Doe"},
    "event":"purchase",
    "properties":{"amount":120,"product":"Shoes"},
    "timestamp":"2026-04-10T12:00:00Z"
  }'
```

### 2) Profil client

Endpoint : `GET /api/customers/{id}`

```bash
curl http://localhost:8080/api/customers/1
```

Retour attendu :

- informations client
- 10 derniers événements
- statistiques agrégées

### 3) Segmentation

Endpoint : `POST /api/segments/query`

```json
{
  "conditions": [
    {
      "event": "purchase",
      "property": "amount",
      "operator": ">",
      "value": 100
    }
  ]
}
```

```bash
curl -X POST http://localhost:8080/api/segments/query \
  -H "Content-Type: application/json" \
  -d '{
    "conditions":[{"event":"purchase","property":"amount","operator":">","value":100}]
  }'
```

## Choix d’architecture

Le backend suit une séparation claire entre :

- `Controller` : réception des requêtes HTTP
- `Service` : logique métier
- `Repository` : accès aux données
- `Validation` : validation des payloads
- `Config/Database` : gestion de la connexion PDO

Cette séparation permet de laisser le code extensible et testable, tout en gardant une taille de projet raisonnable pour un exercice technique.

## Modélisation MySQL

### Choix : table normalisée plutôt que JSON pur

Nous avons choisi une approche normalisée avec quatre tables :

- `customers`
- `event_types` (`id`, `code` unique sur 20 caractères, `label`)
- `events`
- `event_properties`

Chaque événement référence obligatoirement `event_types.id` via `events.event_type_id`. L’ingestion crée automatiquement un type inconnu, avec son code comme libellé.

Pour une base existante, appliquer une seule fois la migration `sql/migrations/20261007_event_types.sql` :

```bash
docker exec -i scal-e-db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE"' < sql/migrations/20261007_event_types.sql
```

Pourquoi ?

- Les filtres de segmentation sur `property_name + value_decimal` ou `value_string` sont beaucoup plus rapides qu’un JSON brut.
- MySQL peut utiliser des index sur des colonnes isolées.
- La requête d’extraction d’un profil client ou d’une segmentation est plus stable et plus lisible.
- Le coût d’une dénormalisation artificielle est évité pour les gros volumes.

Un stockage purement JSON resterait utile pour des données non structurées ou des payloads d’origine très variables, mais il est moins adapté aux requêtes OLAP/analytics rapides sur des propriétés clés.

## Stratégie d’indexation

Les index suivants sont essentiels :

- `customers(email)` unique
- `events(customer_id, event_timestamp)`
- `events(customer_id, event_type_id, event_timestamp)`
- `event_types(code)` unique
- `event_properties(event_id, property_name)`
- `event_properties(property_name, value_decimal)`
- `event_properties(property_name, value_string)`

Cela limite les scans complets et autorise des recherches ciblées sur les propriétés associées aux événements.

## Dashboard minimal

Un petit tableau de bord HTML/JS est fourni dans le dossier `templates/` et servi via la route `/`.

Il permet :

- d’envoyer un événement
- d’exécuter une segmentation visuelle
- de vérifier les réponses JSON directement dans le navigateur

## Tests

Lancer les tests :

```bash
composer test
```

## Pistes d’amélioration pour la production

- Ajout de files de messages (RabbitMQ / Kafka) pour l’ingestion asynchrone
- Cache Redis pour les profils clients et les segmentations fréquentes
- Partitionnement MySQL sur la table `events` par date
- Stockage analytique via un data warehouse pour gros volumes
- Authentification et autorisations sur les endpoints API
- Observabilité : logs structurés, métriques, traces

## Licence

Projet de démonstration technique pour Scal-e.
