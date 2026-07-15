#!/bin/sh
set -e

cd /app/backend

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/public \
  storage/app/private \
  public/uploads/services \
  public/uploads/prestataires

chmod -R ug+rwx storage bootstrap/cache public/uploads || true

# Seed médias locaux vers le volume uploads + storage privé (fichiers absents seulement).
# Le mount Railway sur public/uploads masque le contenu de l'image : on restaure depuis /app/seed.
seed_missing() {
  src_root="$1"
  dest_root="$2"
  [ -d "$src_root" ] || return 0
  find "$src_root" -type f ! -name '.gitignore' 2>/dev/null | while read -r src; do
    rel="${src#"$src_root"/}"
    dest="$dest_root/$rel"
    if [ ! -f "$dest" ]; then
      mkdir -p "$(dirname "$dest")"
      cp "$src" "$dest"
    fi
  done
}
seed_missing /app/seed/uploads /app/backend/public/uploads

# Private media: prefer volume seed tar (full local dump), else image seed.
if [ -f /app/backend/public/uploads/_seed/private.tar ]; then
  mkdir -p /app/backend/storage/app
  tar -xf /app/backend/public/uploads/_seed/private.tar -C /app/backend/storage/app
  echo "Private media restored from volume seed tar."
else
  seed_missing /app/seed/private /app/backend/storage/app/private
fi
echo "Media seed complete."

# Wait for MySQL (best-effort)
if [ -n "${DB_HOST:-}" ]; then
  echo "Waiting for database ${DB_HOST}:${DB_PORT:-3306}..."
  i=0
  while [ "$i" -lt 60 ]; do
    if php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0);} catch (Throwable \$e) { exit(1);}" 2>/dev/null; then
      echo "Database is ready."
      break
    fi
    i=$((i + 1))
    sleep 2
  done
fi

# Import base marketplace schema once (prestataires/clients/services/messagesâ€¦)
php -r '
$host = getenv("DB_HOST") ?: "127.0.0.1";
$port = getenv("DB_PORT") ?: "3306";
$db = getenv("DB_DATABASE") ?: "";
$user = getenv("DB_USERNAME") ?: "";
$pass = getenv("DB_PASSWORD") ?: "";
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = \"prestataires\"")->fetchColumn();
if ($exists > 0) {
  echo "Base schema already present.\n";
  exit(0);
}
$path = "/app/database/db_marketplace.sql";
if (!is_file($path)) {
  fwrite(STDERR, "Missing {$path}\n");
  exit(1);
}
$sql = file_get_contents($path);
$sql = preg_replace("/^--.*$/m", "", $sql);
$sql = preg_replace("/\\/\\*!.*?\\*\\//s", "", $sql);
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach (array_filter(array_map("trim", explode(";", $sql))) as $stmt) {
  if ($stmt === "" || stripos($stmt, "START TRANSACTION") === 0 || stripos($stmt, "COMMIT") === 0 || stripos($stmt, "SET ") === 0) {
    continue;
  }
  try { $pdo->exec($stmt); } catch (Throwable $e) {
    fwrite(STDERR, "Schema import warning: ".$e->getMessage()."\n");
  }
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "Base schema imported.\n";
'

php artisan config:clear || true
php artisan migrate --force --no-interaction

# Recreate caches after env is available at runtime
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Background scheduler (every minute)
(
  while true; do
    php artisan schedule:run --no-interaction >> /tmp/scheduler.log 2>&1 || true
    sleep 60
  done
) &

# Queue worker (database driver)
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> /tmp/queue.log 2>&1 &

PORT="${PORT:-8080}"
echo "Starting BÃ©ninFreelance on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
