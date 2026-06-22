# AGENTS.md

Guide pour les agents de code travaillant sur **gestion-stock-camping**.

## Présentation

Application web de **gestion de stock pour un camping**.

- **Framework** : Symfony 7.4
- **Langage** : PHP ≥ 8.5
- **ORM / base de données** : Doctrine ORM 3 + PostgreSQL 16
- **Vues** : Twig
- **Runtime** : FrankenPHP / Caddy, orchestré via Docker Compose
- **Langue de l'UI** : français (ex. route `/connexion`)

Authentification déjà en place : inscription, vérification d'e-mail, mot de passe oublié
(reset password), « remember me ».

## Commandes

Tout s'exécute dans le conteneur Docker `php`, via le `makefile`. Lance `make help`
pour la liste complète. Principales cibles :

| Commande | Effet |
| --- | --- |
| `make start` | Build + démarre les conteneurs |
| `make up` / `make down` | Démarre / arrête les conteneurs |
| `make build` | Reconstruit les images |
| `make logs` | Logs en direct |
| `make sh` | Shell dans le conteneur `php` |
| `make sf c="<cmd>"` | `bin/console` (ex. `make sf c=about`) |
| `make cc` | Vide le cache (`cache:clear`) |
| `make composer c="<cmd>"` | Composer (ex. `make composer c='req symfony/orm-pack'`) |
| `make migration` | Génère une migration (`make:migration`) |
| `make migrate` | Applique les migrations |
| `make test` | Lance PHPUnit (env `test`) ; options via `c=` |

`jean.json` mappe par ailleurs : `setup`→`make build`, `run`→`make up`, `teardown`→`make down`.

## Architecture

Structure DDD (par contexte métier) :

```
src/
  Domain/<Contexte>/        # Logique métier — ex. src/Domain/Auth/
    Entity/                 #   entités Doctrine
    Repository/             #   repositories
    Form/                   #   form types Symfony
    Security/               #   services liés à la sécurité
  Http/Controller/          # Contrôleurs (routes via attributs #[Route])
  Infrastructure/
    Migrations/             # Migrations Doctrine
  Kernel.php
config/                     # config Symfony (packages/, routes/)
templates/                  # vues Twig
translations/               # traductions
```

- Namespace racine : `App\` → `src/` (PSR-4). Tests : `App\Tests\` → `tests/`.
- `autowire` + `autoconfigure` activés (`config/services.yaml`).
- Routing par **attributs** sur les contrôleurs (`config/routes.yaml` scanne `src/Http/Controller/`).

## Conventions de code

- Respecter le `.editorconfig` du projet.
- Suivre le style existant : **double quotes**, attributs PHP pour Doctrine et les routes.
- Les entités utilisateur implémentent `UserInterface` / `PasswordAuthenticatedUserInterface`.
- Échapper les noms de table réservés avec des backticks, ex. la table `user` :

```php
#[ORM\Table(name: "`user`")]
```

## Base de données & migrations

⚠️ **Ne jamais réécrire une migration déjà exécutée.** Doctrine ne rejoue pas une version
présente dans `doctrine_migration_versions` ; modifier son `up()` n'a aucun effet en base.
Crée toujours une **nouvelle** migration (timestamp ultérieur) appliquant le diff manquant.

Vérifier l'état : `make sf c="doctrine:migrations:list"`. Détails dans `.ai/lessons.md`.

## Tests

- `make test` lance PHPUnit dans l'env `test`. Options : `make test c="--group e2e --stop-on-failure"`.
- Il n'y a pas encore de dossier `tests/` : créer les tests sous `tests/` (namespace `App\Tests\`).

## Sécurité & secrets

- La config DB / Mailer provient de `.env` et `.env.local`. **Ne jamais committer ni recopier
  de secrets** (le `DATABASE_URL` contient un mot de passe réel).
- Sécurité applicative configurée dans `config/packages/security.yaml`
  (firewall `main`, `form_login` avec CSRF, `remember_me`).

## Workflow agent

- Lire `.ai/lessons.md` en début de session ; le compléter après toute correction.
- Utiliser `.ai/todo.md` pour le suivi des tâches.
- Le Dev Container / firewall pour agents autonomes est documenté dans `docs/agents.md`.
