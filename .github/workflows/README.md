# 🔄 GitHub Workflows - HardwareHouse

Ce dossier contient tous les workflows GitHub Actions pour l'automatisation CI/CD du projet HardwareHouse.

## 📋 Vue d'ensemble des Workflows

### 🌊 Pipeline Principal
```
dev → test (auto) → main (PR manuelle) → production (auto)
```

## 📁 Workflows Disponibles

### 🔨 **Workflows Modulaires** (Réutilisables)

#### `quality.yml`
**Déclenchement :** `workflow_call`  
**Objectif :** Analyse de la qualité du code  
**Tâches :**
- ✅ Configuration PHP 8.4 + Composer
- 📦 Cache optimisé des dépendances
- 🔍 ECS (Easy Coding Standard) avec autofix
- 🔧 Rector avec autofix
- 📝 Lint YAML configs
- 🎨 Lint templates Twig
- 🏗️ Lint container DI
- 🧪 PHPStan analyse statique (niveau max)

#### `audit.yml`
**Déclenchement :** `workflow_call`  
**Objectif :** Audit de sécurité  
**Tâches :**
- 🔒 Audit sécurité Composer
- 📊 Ignore vulnérabilités low/medium
- 📋 Génération rapport JSON
- ⬆️ Upload artifacts pour consultation

#### `test.yml`
**Déclenchement :** `workflow_call`  
**Objectif :** Exécution des tests  
**Tâches :**
- 🐘 Base PostgreSQL 16
- 🧪 Exécution suite de tests PHPUnit
- 🗃️ Tests base de données avec fixtures
- ⚡ Cache optimisé des dépendances

---

### 🏗️ **Workflows d'Intégration** (Branches)

#### `ci-dev.yml`
**Déclenchement :** Push/PR sur `dev`  
**Objectif :** Validation et auto-merge vers test  
**Pipeline :**
1. 🔍 **Audit sécurité** (`audit.yml`)
2. ✅ **Analyse qualité** (`quality.yml`) 
3. 🧪 **Tests complets** (`test.yml`)
4. 🔀 **Auto-merge vers `test`** (si succès)

**Permissions :** `contents: write`  
**Secrets requis :** `PAT_TOKEN`

#### `ci-test.yml`
**Déclenchement :** Push sur `test`  
**Objectif :** Validation et création PR vers main  
**Pipeline :**
1. 🔍 **Re-audit sécurité** 
2. ✅ **Re-analyse qualité**
3. 🧪 **Re-tests complets**
4. 📝 **Création PR automatique vers `main`**

**Permissions :** `contents: write`, `pull-requests: write`  
**PR générée :** 
- Titre : "🚀 Deploy to Production - Auto PR from test"
- Corps : Résumé des validations passées

#### `ci-main.yml`
**Déclenchement :** Push sur `main`  
**Objectif :** Déploiement production  
**Pipeline :**
1. 🔍 **Triple validation** (audit + qualité + tests)
2. 🚀 **Déploiement production optimisé**

**Environnement :** `production`  
**Timeout :** 15 minutes  
**Concurrency :** Protection contre déploiements simultanés

---

## 🚀 Détails du Déploiement Production

### Étapes du déploiement (`ci-main.yml`) :

#### **Phase 1: Validation Locale**
- ✅ Checkout optimisé (`fetch-depth: 1`)
- 🐘 Setup PHP 8.4 + extensions
- 📦 Cache Composer intelligent
- 🔍 Validation `composer.json`
- 🧪 Dry-run installation

#### **Phase 2: Déploiement SSH**
- 📦 **Sauvegarde automatique** avec timestamp
- 🔧 **Mode maintenance** temporaire
- 📥 **Git fetch + reset hard** vers `main`
- 📦 **Installation dépendances** optimisée
- 🎨 **Build assets** (Asset Mapper + Tailwind)
- 🗃️ **Migrations base de données**
- 🧹 **Cache clear + warmup** Symfony
- 🔐 **Permissions** www-data
- 🔄 **Restart services** (PHP-FPM + Nginx)
- 🩺 **Health check** post-déploiement

### Optimisations Performance :
- 🚀 `--classmap-authoritative` Composer
- ⚡ Cache Symfony pré-chauffé
- 🎯 `--minify` pour Tailwind CSS
- 🔧 Mode maintenance pour zero-downtime

## 🔐 Secrets Requis

### **Déploiement**
```env
HOST=ip-de-votre-vps
USERNAME=deploy
PORT=22
SSH_PRIVATE_KEY=clé-privée-ssh
PROJECT_PATH=/var/www/hardwarehouse
```

### **Tests**
```env
POSTGRES_DB=hardwarehouse_test
POSTGRES_USER=postgres
POSTGRES_PASSWORD=motdepasse
```

### **Auto-merge**
```env
PAT_TOKEN=ghp_xxxxxxxxxxxx
```

## 🔧 Configuration du Serveur

### Prérequis VPS :
- 🐘 PHP 8.4 + extensions (ctype, iconv, json, mbstring, pdo_mysql)
- 🎼 Composer 2.x
- 🔗 Git
- 🌐 Nginx/Apache
- 🗃️ MySQL/MariaDB
- 🔑 Utilisateur `deploy` avec sudo

### Structure recommandée :
```
/var/www/hardwarehouse/          # Projet principal
/var/backups/hardwarehouse/      # Sauvegardes auto
├── backup-20240129_143022/      # Backup horodaté
├── backup-20240129_151045/      # Backup horodaté
```

## 📊 Monitoring

### **Logs disponibles :**
- 📋 GitHub Actions (interface web)
- 🗂️ Artifacts d'audit sécurité
- 📁 Logs serveur : `/var/log/hardwarehouse-deploy.log`
- 🐘 Logs Symfony : `var/log/`

### **Health checks :**
- ✅ Status codes déploiement
- 🩺 `php bin/console about` post-déploiement
- 🔍 Validation permissions fichiers

## 🆘 Dépannage Rapide

### **Déploiement échoué :**
```bash
# Restaurer depuis backup
cd /var/www/
sudo rm -rf hardwarehouse/
sudo cp -r /var/backups/hardwarehouse/backup-YYYYMMDD_HHMMSS/ hardwarehouse/
sudo chown -R deploy:www-data hardwarehouse/
```

### **Cache corrompu :**
```bash
php bin/console cache:clear --env=prod
rm -rf var/cache/prod/
```

### **Re-run workflow :**
- Interface GitHub → Actions → Re-run failed jobs

## 📈 Métriques Performance

### **Temps d'exécution typiques :**
- 📊 Quality check : ~2-3 minutes
- 🔒 Security audit : ~1-2 minutes  
- 🧪 Tests : ~3-5 minutes
- 🚀 Déploiement : ~3-4 minutes

### **Optimisations actives :**
- 📦 Cache Composer partagé
- ⚡ Restoration en cascade
- 🔧 Concurrency control
- ⏱️ Timeouts configurés

---

## 🔄 Workflow de Développement

### **Processus recommandé :**
1. 💻 Développement sur `dev`
2. 🔄 Push → CI automatique
3. ✅ Si succès → Auto-merge vers `test`
4. 🧪 Tests sur environnement test
5. 📝 PR automatique vers `main`
6. 👨‍💼 **Review manuelle** + merge
7. 🚀 **Déploiement automatique** en production

### **Points de contrôle :**
- ✋ **Seule étape manuelle :** Validation de la PR `test → main`
- 🔒 **Triple sécurité :** Tests sur dev, test, et main
- 🛡️ **Protection :** Environment production avec review

---

*Ce système garantit une pipeline robuste, rapide et sécurisée pour vos déploiements ! 🎉*