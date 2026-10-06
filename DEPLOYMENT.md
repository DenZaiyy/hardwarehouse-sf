# Guide de Déploiement CI/CD - HardwareHouse

Ce guide vous explique comment configurer le déploiement automatique complet de votre application Symfony sur votre VPS OVH via GitHub Actions.

> Pour la configuration réseau en production (Cloudflare, verrouillage de l'origine, pare-feu), voir [INFRASTRUCTURE.md](INFRASTRUCTURE.md).

## Pipeline de Déploiement

```mermaid
graph LR
    A[dev] -->|Push + CI| B[test]
    B -->|CI + Tests| P[Préproduction]
    P -->|Déploiement réussi| C[PR to main]
    C -->|Manual Merge| D[main]
    D -->|Auto Deploy| E[Production]
```

### Workflow Automatisé :
1. **`dev`** → Push → Quality + Audit + Tests → Auto-merge vers `test`
2. **`test`** → Re-tests → Déploiement en préproduction (`test.hardwarehouse.fr`) → Création PR automatique vers `main`
3. **`main`** → Merge manuel → Triple validation → Déploiement production

## Prérequis

### Sur votre VPS OVH :
- **PHP 8.4+** avec extensions (ctype, iconv, json, mbstring, pdo_mysql)
- **Composer 2.x**
- **Git**
- **Nginx ou Apache**
- **MySQL/MariaDB** ou **PostgreSQL**
- **Accès SSH** configuré avec clés

### Sur GitHub :
- Repository avec branches `dev`, `test`, `main`
- Permissions Actions activées
- Secrets configurés (voir section dédiée)

## Configuration du Serveur

### 1. Structure recommandée
```bash
/var/www/hardwarehouse/              # Projet principal
/var/backups/hardwarehouse/          # Sauvegardes automatiques
├── backup-20240129_143022/          # Backup horodaté
├── backup-20240129_151045/          # Backup horodaté
```

### 2. Utilisateur de déploiement
```bash
# Créer utilisateur deploy
sudo adduser deploy
sudo usermod -aG www-data deploy
sudo usermod -aG sudo deploy

# Configuration SSH
sudo -u deploy ssh-keygen -t rsa -b 4096 -C "deploy@hardwarehouse"
```

### 3. Permissions et propriétés
```bash
# Ownership
sudo chown -R deploy:www-data /var/www/hardwarehouse/

# Permissions Symfony
sudo chmod -R 775 /var/www/hardwarehouse/var/
sudo chmod -R 755 /var/www/hardwarehouse/public/
sudo chmod +x /var/www/hardwarehouse/bin/console
```

### 4. Configuration Nginx & SSL

> **Votre VPS dispose déjà de :**
> - Configuration Nginx optimisée pour votre domaine
> - Certificat SSL via Certbot (Let's Encrypt)
> - Renouvellement automatique HTTPS

> Le site étant proxifié par Cloudflare en production, la configuration Nginx réelle inclut aussi le module `real_ip` et l'Authenticated Origin Pulls (mTLS) — détaillés dans [INFRASTRUCTURE.md](INFRASTRUCTURE.md#5-restitution-de-lip-réelle-du-visiteur-real_ip). L'exemple ci-dessous ne couvre que la base Nginx/Certbot.

**Configuration recommandée pour le mode maintenance :**
```nginx
server {
    listen 443 ssl http2;
    server_name votre-domaine.com;

    # Certificats SSL (gérés par Certbot)
    ssl_certificate /etc/letsencrypt/live/votre-domaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.com/privkey.pem;

    root /var/www/hardwarehouse/public;
    index index.php;

    # Mode maintenance pour déploiements zero-downtime
    if (-f /var/www/hardwarehouse/var/maintenance.flag) {
        return 503;
    }

    error_page 503 @maintenance;
    location @maintenance {
        rewrite ^(.*)$ /maintenance.html break;
    }

    # Configuration Symfony existante...
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
}

# Redirection HTTP vers HTTPS (probablement déjà configurée)
server {
    listen 80;
    server_name votre-domaine.com;
    return 301 https://$server_name$request_uri;
}
```

**Vérification SSL :**
```bash
# Statut du certificat
sudo certbot certificates

# Test renouvellement
sudo certbot renew --dry-run
```

## Secrets GitHub Requis

Dans `Settings > Secrets and variables > Actions` :

### **Déploiement SSH**
```env
HOST=123.45.67.89                    # IP de votre VPS
USERNAME=deploy                      # Utilisateur SSH
PORT=22                              # Port SSH
SSH_PRIVATE_KEY=-----BEGIN RSA...    # Clé privée SSH complète
SSH_PASSPHRASE=ma-passphrase         # Passphrase de la clé (si protégée)
SSH_PASSWORD=mot-de-passe-user       # Mot de passe utilisateur deploy
PROJECT_PATH=/var/www/hardwarehouse  # Chemin du projet
```

### **Base de données (Tests)**
> **Configuration PostgreSQL automatique**
> Les tests utilisent maintenant une configuration PostgreSQL intégrée sans secrets requis.

### **Préproduction** (environnement GitHub `preprod`)
```env
PREPROD_PROJECT_PATH=/var/www/hardwarehouse-test   # Chemin de la préproduction, distinct de PROJECT_PATH
```
> Si `HOST`, `USERNAME`, `PORT`, `SSH_PRIVATE_KEY` et `SSH_PASSWORD` sont rattachés à l'environnement `prod`, les recopier aussi dans `preprod`.

### **Auto-merge dev→test**
```env
PAT_TOKEN=ghp_xxxxxxxxxxxx           # Personal Access Token GitHub
```
> **Créer PAT :** GitHub → Settings → Developer settings → Personal access tokens → Generate new token
> **Permissions :** `repo`, `workflow`, `write:packages`

## Workflows et Processus

### **ci-dev.yml** - Branche `dev`
**Déclenchement :** Push ou PR sur `dev`
```
Jobs:
├── quality     # ECS, Rector, PHPStan, Lint
├── audit       # Composer security audit
├── tests       # PHPUnit avec PostgreSQL
└── auto-merge  # Auto-merge vers test si succès
```

### **ci-test.yml** - Branche `test`
**Déclenchement :** Push sur `test`
```
Jobs:
├── quality      # Re-validation qualité
├── audit        # Re-audit sécurité
├── tests        # Re-tests complets
├── deploy-test  # Déploiement en préproduction (voir section dédiée)
└── create-pr    # PR automatique vers main, seulement si la préproduction est à jour
```

### **ci-main.yml** - Branche `main`
**Déclenchement :** Push sur `main` (après merge PR)
```
Jobs:
├── quality          # Triple validation
├── audit            # Triple audit
├── tests            # Triple tests
└── deploy-production # Déploiement VPS
```

## Préproduction (`test.hardwarehouse.fr`)

La branche `test` est déployée sur une seconde instance du même VPS avant toute PR vers `main` : la PR
n'est créée que si ce déploiement réussit. La préproduction exécute le même `make prod` que la
production, dans son propre dossier, avec sa propre base et ses propres clés (Stripe en mode test,
aucun e-mail réel).

### Mise en place sur le serveur (une seule fois)

1. **DNS (Cloudflare)** : enregistrement `test` proxifié, comme le domaine principal et `api`.
2. **Code** :
   ```bash
   sudo -u deploy git clone <url-du-depot> /var/www/hardwarehouse-test
   cd /var/www/hardwarehouse-test && sudo -u deploy git checkout test
   ```
3. **Base PostgreSQL dédiée** :
   ```bash
   sudo -u postgres createuser --pwprompt hardwarehouse_test
   sudo -u postgres createdb --owner=hardwarehouse_test hardwarehouse_test
   ```
4. **`.env.local`** (jamais versionné) :
   ```env
   APP_ENV=prod
   APP_SECRET=<nouvelle valeur, différente de la production>
   DEFAULT_URI=https://test.hardwarehouse.fr
   DATABASE_URL="postgresql://hardwarehouse_test:<mot-de-passe>@127.0.0.1:5432/hardwarehouse_test?serverVersion=16&charset=utf8"
   API_BASE_URL=https://api.hardwarehouse.fr   # API de production, lue seulement
   MAILER_DSN=null://null                      # aucun e-mail réel depuis la préproduction
   MESSENGER_TRANSPORT_DSN=<identique à la production>
   STRIPE_SECRET_KEY=sk_test_...               # clés de test uniquement
   STRIPE_WEBHOOK_SECRET=whsec_...             # secret du point de terminaison de test (étape 6)
   RECAPTCHA3_KEY=...                          # clé autorisant test.hardwarehouse.fr (étape 7)
   RECAPTCHA3_SECRET=...
   SYMFONY_TRUSTED_PROXIES=<identique à la production>
   ```
5. **Nginx** : dupliquer le bloc serveur de production en remplaçant `server_name` par
   `test.hardwarehouse.fr`, `root` par `/var/www/hardwarehouse-test/public` et le chemin du
   `maintenance.flag`, avec un certificat valide pour ce domaine et la même configuration Cloudflare
   (`real_ip`, Authenticated Origin Pulls, voir [INFRASTRUCTURE.md](INFRASTRUCTURE.md)). Ajouter :
   ```nginx
   # La préproduction ne doit pas être référencée
   add_header X-Robots-Tag "noindex, nofollow" always;

   # Accès réservé (fichier créé avec htpasswd)
   auth_basic "Préproduction HardWareHouse";
   auth_basic_user_file /etc/nginx/.htpasswd-hardwarehouse-test;

   # Stripe doit pouvoir notifier les paiements sans authentification
   location = /webhook/stripe {
       auth_basic off;
       try_files $uri /index.php$is_args$args;
   }
   ```
6. **Stripe (environnement de test)** : dans Workbench, onglet **Webhooks**, ajouter une destination
   « endpoint de webhook » vers `https://test.hardwarehouse.fr/webhook/stripe`, avec les événements
   traités par `StripeWebhookController` (les mêmes qu'en production) : `checkout.session.completed`,
   `checkout.session.expired`, `payment_intent.succeeded`, `payment_intent.payment_failed`,
   `payment_intent.canceled`, `charge.refunded` et `charge.dispute.created`. Reporter ensuite son secret
   `whsec_…` dans `STRIPE_WEBHOOK_SECRET`. Ce secret appartient au point de terminaison : changer les clés
   API ne le modifie pas, mais chaque environnement Stripe (production, environnement de test) a ses
   propres points de terminaison. En local, Stripe ne peut pas joindre `127.0.0.1` : la CLI Stripe relaie
   les événements et affiche son secret (`stripe listen --print-secret`), à mettre dans `.env.dev`, le
   fichier local du développement, ignoré par Git et chargé après `.env.local` (`--skip-verify` si le
   certificat local de Symfony est refusé) :
   ```bash
   # Depuis la CLI 1.5x, les événements relayés doivent être nommés (ou --all-snapshot pour tous)
   stripe listen \
     --events checkout.session.completed,checkout.session.expired,payment_intent.succeeded,payment_intent.payment_failed,payment_intent.canceled,charge.refunded,charge.dispute.created \
     --forward-to https://127.0.0.1:8000/webhook/stripe
   ```
7. **reCAPTCHA** : ajouter `test.hardwarehouse.fr` aux domaines autorisés de la clé, ou créer une clé dédiée.
8. **GitHub** : créer l'environnement `preprod` (Settings > Environments) et ses secrets (voir
   [Secrets GitHub Requis](#secrets-github-requis)). Le job refuse de s'exécuter si
   `PREPROD_PROJECT_PATH` est vide ou identique à `PROJECT_PATH`.
9. **Premier déploiement** : lancer `make prod` à la main dans le dossier (création de la base et
   migrations), puis vérifier le site avant de pousser sur `test`.

### Limite connue

La préproduction lit l'API de production. Depuis que la boutique fait sortir du stock les articles
des commandes payées (`POST /api/v1/stock-exits`), une commande de test en préproduction décrémenterait
le stock réel : **laisser `SHOP_API_TOKEN` vide en préproduction**. Les sorties de stock y partent alors
dans la file des échecs avec un message explicite, jusqu'à ce qu'une API de préproduction existe.

## Processus de Déploiement Production

### **Phase 1 : Validation Locale**
- Checkout optimisé (`fetch-depth: 1`)
- Setup PHP 8.4 + Composer
- Cache Composer intelligent
- Validation `composer.json`
- Dry-run installation

### **Phase 2 : Déploiement SSH Optimisé**
```bash
# Sauvegarde automatique
sudo cp -r $PROJECT_PATH /var/backups/hardwarehouse/backup-$(date +%Y%m%d_%H%M%S)

# Mode maintenance temporaire
touch var/maintenance.flag

# Mise à jour code
git fetch origin --prune
git reset --hard origin/main

# Dépendances optimisées
composer install --no-dev --optimize-autoloader --classmap-authoritative

# Assets avec cache
php bin/console asset-map:compile
php bin/console importmap:install
php bin/console tailwind:build --minify

# Base de données
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Cache Symfony optimisé
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

# Permissions et services
sudo chown -R www-data:www-data var/ public/
sudo systemctl reload php8.4-fpm
sudo nginx -t && sudo systemctl reload nginx

# Fin mode maintenance
rm -f var/maintenance.flag

# Health check
php bin/console about --env=prod
```

## Optimisations Performance

### **Cache Strategy**
- Cache Composer partagé entre jobs
- Clés de cache spécialisées par workflow
- Restoration en cascade pour maximiser hits

### **Déploiement Optimizations**
- `--classmap-authoritative` Composer
- Cache Symfony pré-chauffé
- `--minify` Tailwind CSS
- Mode maintenance zero-downtime
- Concurrency control production

## Variables d'Environnement Serveur

Créez `/var/www/hardwarehouse/.env.local` :
```bash
# Production Environment
APP_ENV=prod
APP_SECRET=votre-secret-32-caracteres-aleatoires
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/hardwarehouse_prod?serverVersion=16&charset=utf8"
MAILER_DSN=smtp://localhost:587

# Sorties de stock : même valeur que la variable SHOP_API_TOKEN de l'API (openssl rand -hex 32)
SHOP_API_TOKEN=...

# Facultatif : identité du vendeur sur les factures (valeurs fictives par défaut, voir config/services.yaml)
# INVOICE_SELLER_NAME, INVOICE_SELLER_ADDRESS, INVOICE_SELLER_SIRET, INVOICE_SELLER_VAT_NUMBER, INVOICE_NOTICE

# Cache & Performance
REDIS_URL=redis://localhost:6379
OPCACHE_ENABLE=1

# Monitoring
LOG_LEVEL=error
```

## Workflow de Développement

### **Développement Quotidien**
```bash
# 1. Travail sur dev
git checkout dev
git pull origin dev

# 2. Développement + commits
git add .
git commit -m "feat: nouvelle fonctionnalité"

# 3. Push → déclenchement CI automatique
git push origin dev
# → Quality check → Audit → Tests → Auto-merge vers test

# 4. Validation sur test
# → Tests sur environnement test → PR automatique vers main

# 5. Review et merge manuel
# GitHub Interface : Review PR test→main → Merge

# 6. Déploiement automatique
# → Triple validation → Déploiement production
```

### **Points de Contrôle**
- **Seule action manuelle :** Merge PR `test → main`
- **Triple sécurité :** Tests sur dev, test, et main
- **Protection :** Environment production avec review
- **Monitoring :** Logs à chaque étape

## Monitoring et Logs

### **GitHub Actions**
- Interface Actions pour tous les workflows
- Artifacts d'audit sécurité téléchargeables
- Métriques de performance par job

### **Serveur Production**
```bash
# Logs de déploiement
tail -f /var/log/syslog | grep deploy

# Logs Symfony
tail -f /var/www/hardwarehouse/var/log/prod.log

# Logs Nginx
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Status services
systemctl status php8.4-fpm nginx mysql
```

### **Health Checks**
```bash
# Application Symfony
php bin/console about --env=prod

# Database connectivity
php bin/console doctrine:query:sql "SELECT 1"

# Cache status
ls -la var/cache/prod/

# Permissions
find var/ -not -writable -type d
```

## Dépannage Avancé

### **Échec de Déploiement**
```bash
# 1. Consulter logs GitHub Actions
# 2. Restaurer automatiquement depuis backup
cd /var/www/
sudo rm -rf hardwarehouse/
sudo cp -r /var/backups/hardwarehouse/backup-YYYYMMDD_HHMMSS/ hardwarehouse/
sudo chown -R deploy:www-data hardwarehouse/

# 3. Re-lancer workflow manuellement
# GitHub Interface → Actions → Re-run failed jobs
```

### **Problèmes Fréquents**

#### **Timeout SSH**
```yaml
# Dans ci-main.yml, ajustez :
timeout: 600s
```

#### **Permissions**
```bash
sudo chown -R www-data:www-data /var/www/hardwarehouse/var/
sudo chmod -R 775 /var/www/hardwarehouse/var/
```

#### **Cache Symfony Corrompu**
```bash
rm -rf var/cache/prod/
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

#### **Assets Non Générés**
```bash
php bin/console asset-map:compile
php bin/console importmap:install
php bin/console tailwind:build --minify
```

## Métriques de Performance

### **Temps d'Exécution Moyens**
- **Quality Analysis :** ~2-3 min
- **Security Audit :** ~1-2 min
- **Tests Suite :** ~3-5 min
- **Production Deploy :** ~3-4 min
- **Total Pipeline :** ~10-15 min

### **Optimisations Actives**
- Cache hits Composer : ~80%
- Parallel job execution
- Incremental builds
- Selective cache invalidation

## Ressources Utiles

### **Documentation**
- [INFRASTRUCTURE.md](INFRASTRUCTURE.md) — Cloudflare, verrouillage de l'origine, pare-feu réseau
- [Symfony Deployment](https://symfony.com/doc/current/deployment.html)
- [GitHub Actions](https://docs.github.com/en/actions)
- [Composer Optimization](https://getcomposer.org/doc/articles/autoloader-optimization.md)

### **Support**
En cas de problème, vérifiez dans l'ordre :
1. Logs GitHub Actions
2. Logs serveur production
3. Logs application Symfony
4. Status services système

---

**Votre pipeline CI/CD est maintenant complètement automatisé et optimisé.**

## Worker Messenger (sorties de stock)

Quand une commande est payée, le webhook Stripe met en file un message `RecordOrderStockExit` (transport
`async`) : la réponse à Stripe n'attend pas l'API du catalogue. Un worker envoie ensuite les lignes de la
commande à `POST /api/v1/stock-exits`. **Sans worker, les messages restent dans `messenger_messages` et
le stock ne baisse jamais.**

Créer le service avec l'éditeur de systemd, qui évite les pièges du copier-coller dans le shell (lignes
repliées, espaces ajoutés) :
```bash
sudo systemctl edit --force --full hardwarehouse-messenger.service
```
Contenu, en remplaçant `/chemin/du/projet` par le chemin **absolu** du projet sur le serveur (systemd ne
comprend pas `~`) ; un `\` doit être le dernier caractère de sa ligne :
```ini
[Unit]
Description=HardWareHouse worker Messenger
After=network.target postgresql.service

[Service]
User=www-data
WorkingDirectory=/chemin/du/projet
ExecStart=/usr/bin/php8.4 \
  /chemin/du/projet/bin/console \
  messenger:consume async \
  --time-limit=3600 \
  --memory-limit=128M --env=prod
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```
```bash
sudo systemd-analyze verify /etc/systemd/system/hardwarehouse-messenger.service   # rien = valide
systemctl show hardwarehouse-messenger -p ExecStart --no-pager                    # commande lancée
sudo systemctl enable --now hardwarehouse-messenger
systemctl status hardwarehouse-messenger --no-pager                              # active (running)
sudo journalctl -u hardwarehouse-messenger -n 50 --no-pager                      # sudo : groupe adm requis sinon
php bin/console messenger:stats --env=prod                                       # messages en attente
```
`www-data` (l'utilisateur de PHP-FPM) doit pouvoir lire le projet et écrire dans `var/`. En production,
le worker ne journalise que les erreurs ; `messenger:stats` montre la file.

`make prod` termine par `messenger:stop-workers` : le worker s'arrête proprement après son message en
cours et systemd le relance avec le nouveau code.

**Reprises et échecs.** Une API injoignable ou en erreur fait réessayer le message 3 fois, avec un délai
croissant ; il passe ensuite dans la file `failed`. Un refus n'est pas réessayé : un stock insuffisant
(409) envoie aussi une alerte à `ADMIN_EMAIL`, pour décider entre réassort et remboursement ; un jeton
refusé ou absent part directement dans la file `failed`. Après correction :
```bash
php bin/console messenger:failed:show --env=prod
php bin/console messenger:failed:retry --env=prod
```

**Côté API**, une seule fois : définir `SHOP_API_TOKEN` (même valeur que la boutique) dans les variables
d'environnement Vercel, puis créer l'index unique qui rend la sortie idempotente avec
`npm run db:push` sur la base de production.

**Factures.** Les PDF sont rangés dans `var/invoices/prod/AAAA/`, hors de `public/` : ils sont servis
uniquement à leur titulaire par `/profile/orders/{référence}/invoice`. Le dossier `var/` fait partie de
la sauvegarde automatique du déploiement.
