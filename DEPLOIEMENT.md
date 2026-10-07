# Déploiement de l'API GETTESTED

## Image

```bash
docker build --target prod -t gettested-api .
```

L'image tourne en `APP_ENV=prod`, sans debug, en mode worker FrankenPHP. Elle ne contient aucun secret
(voir `.dockerignore`) : tout est fourni à l'exécution.

## Variables d'environnement obligatoires

| Variable | Rôle |
|---|---|
| `APP_SECRET` | Secret Symfony : **nouvelle valeur aléatoire** (celle de `.env` est publique) |
| `DATABASE_URL` | PostgreSQL 16 |
| `JWT_PASSPHRASE` | Passphrase des clés JWT : **nouvelle valeur** |
| `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` | Clés Stripe live |
| `STRIPE_APPLICATION_FEE_PERCENT` | Commission de la plateforme (10 par défaut) |
| `MAILER_DSN` | Serveur d'envoi des emails |
| `FRONTEND_URL` | URL du front (liens des emails, retours Stripe) |
| `CORS_ALLOW_ORIGIN` | Regex de l'origine du front, par ex. `^https://app\.gettested\.fr$` |

Clés JWT : monter `config/jwt/private.pem` et `config/jwt/public.pem` (générées avec
`php bin/console lexik:jwt:generate-keypair` et la nouvelle passphrase).

## Base de données

```bash
# Base vierge
php bin/console doctrine:migrations:migrate -n

# Base existante créée avant octobre 2026 (ancien historique de migrations) : schéma déjà à jour,
# on remplace seulement l'historique par la migration de départ
php bin/console doctrine:schema:validate          # doit être « in sync »
php bin/console doctrine:migrations:rollup -n
```

Données de référence d'une base vierge :

```bash
php bin/console app:seed-document-types                  # types de documents + modèles
php bin/console app:seed-convocation-template
php bin/console app:seed-attestation-presence-template
```

Les pays et les langues ne sont chargés aujourd'hui que par les fixtures (non installées en production) :
à importer depuis une base de dev (`pg_dump -t country -t language -t country_language --data-only`).

Chaque évolution du schéma passe ensuite par `doctrine:migrations:diff` puis `migrate` (plus de `schema:update`).

## Tâches planifiées (cron)

```cron
*/10 * * * *  php bin/console app:exams:close-expired        # épreuves en ligne abandonnées / absents
0 8 * * *     php bin/console app:sessions:send-reminders    # rappel J-2 aux candidats
0 * * * *     php bin/console app:sessions:auto-lock          # verrouillage à la date limite d'inscription
0 3 * * *     php bin/console gesdinet:jwt:clear              # purge des refresh tokens expirés
```

## Front (Next.js)

- `API_SERVER_URL` : URL de l'API vue par le serveur Next.
- Le certificat de l'API est vérifié en production ; `API_TLS_INSECURE=true` uniquement pour une API
  interne sans autorité de certification reconnue.
