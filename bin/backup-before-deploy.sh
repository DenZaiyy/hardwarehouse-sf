#!/bin/sh
# Sauvegarde lancée par ci-main.yml avant chaque mise en production, depuis le dossier du projet et avant
# toute modification : si elle échoue, le déploiement s'arrête et le site reste tel quel.
# Elle garde ce que Git ne sait pas reconstruire :
# - la base PostgreSQL, que make prod va migrer (format de pg_restore) ;
# - la configuration du serveur, les factures et les images envoyées ;
# - le commit en production, pour revenir au code précédent.
# Seules les KEEP dernières sauvegardes sont conservées. Restauration : DEPLOYMENT.md.
#
# Usage : sh bin/backup-before-deploy.sh <dossier des sauvegardes>
# Affiche le dossier de la sauvegarde créée.
set -eu

BACKUP_ROOT=$1
KEEP=5
BACKUP="$BACKUP_ROOT/deploy-$(date +%Y%m%d_%H%M%S)"

# Secrets et données personnelles des clients : lisibles par le seul compte du déploiement
umask 077
mkdir -p "$BACKUP"

git rev-parse HEAD > "$BACKUP/commit.txt"

# Identifiants lus comme Symfony les lit (.env, .env.local, variables du serveur), passés à pg_dump par
# l'environnement : le mot de passe n'apparaît pas dans la liste des processus
# shellcheck disable=SC2016 # code PHP entre apostrophes : ses $ ne sont pas ceux du shell
DATABASE_ENV=$(php -r '
require "vendor/autoload.php";
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
$url = parse_url((string) ($_SERVER["DATABASE_URL"] ?? ""));
if (false === $url || !isset($url["host"], $url["path"])) {
    fwrite(STDERR, "DATABASE_URL is missing or is not a URL\n");
    exit(1);
}
foreach ([
    "PGHOST" => $url["host"],
    "PGPORT" => $url["port"] ?? 5432,
    "PGUSER" => rawurldecode($url["user"] ?? ""),
    "PGPASSWORD" => rawurldecode($url["pass"] ?? ""),
    "PGDATABASE" => ltrim($url["path"], "/"),
] as $name => $value) {
    echo "export ", $name, "=", escapeshellarg((string) $value), "\n";
}
')
eval "$DATABASE_ENV"
pg_dump --format=custom --file="$BACKUP/database.dump"

# Fichiers ignorés par Git qui n'existent que sur le serveur
FILES=""
for path in .env.local .env.prod.local var/invoices public/uploads; do
    if [ -e "$path" ]; then
        FILES="$FILES $path"
    fi
done
if [ -n "$FILES" ]; then
    # shellcheck disable=SC2086 # chemins fixes et sans espace, découpés volontairement
    tar -czf "$BACKUP/files.tar.gz" $FILES
fi

# Les noms datés se trient dans l'ordre chronologique : tout ce qui suit les KEEP plus récentes est supprimé.
# Seuls les dossiers deploy-* sont concernés.
find "$BACKUP_ROOT" -maxdepth 1 -type d -name 'deploy-*' | sort -r | tail -n +$((KEEP + 1)) | while read -r old; do
    rm -rf -- "$old"
done

echo "$BACKUP"
