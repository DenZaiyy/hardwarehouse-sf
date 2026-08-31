# Guide de Déploiement CI/CD - HardwareHouse

Ce guide vous explique comment configurer le déploiement automatique complet de votre application Symfony sur votre VPS OVH via GitHub Actions.

> Pour la configuration réseau en production (Cloudflare, verrouillage de l'origine, pare-feu), voir [INFRASTRUCTURE.md](INFRASTRUCTURE.md).

## Pipeline de Déploiement

```mermaid
graph LR
    A[dev] -->|Push + CI| B[test]
    B -->|CI + Tests| C[PR to main]
    C -->|Manual Merge| D[main]
    D -->|Auto Deploy| E[Production]
```

### Workflow Automatisé :
1. **`dev`** → Push → Quality + Audit + Tests → Auto-merge vers `test`
2. **`test`** → Re-tests → Création PR automatique vers `main`
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
├── quality     # Re-validation qualité
├── audit       # Re-audit sécurité
├── tests       # Re-tests complets
└── create-pr   # PR automatique vers main
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
DATABASE_URL="mysql://user:password@127.0.0.1:3306/hardwarehouse_prod"
MAILER_DSN=smtp://localhost:587

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
