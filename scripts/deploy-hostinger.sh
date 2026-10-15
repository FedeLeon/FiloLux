#!/usr/bin/env bash
# Run by GitHub Actions after Composer installation. No credentials in source.
set -euo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
public_path="${HOSTINGER_DEPLOY_PATH:?Set HOSTINGER_DEPLOY_PATH to the absolute Hostinger public_html path}"
ssh_config="${FILOLUX_SSH_CONFIG:-$HOME/.ssh/filolux_config}"
php_override="${HOSTINGER_PHP_BIN:-}"

# Restrict destinations before sending any commands or files to the server.
[[ "$public_path" =~ ^/[a-zA-Z0-9_./-]+/public_html$ ]] || { echo 'Deploy path must be an absolute path ending in /public_html' >&2; exit 1; }
[[ "$public_path" != *'/../'* && "$public_path" != *'/./'* && "$public_path" != *'//'* ]] || { echo 'Deploy path must not contain traversal or repeated slashes' >&2; exit 1; }
private_path="${public_path%/public_html}/filolux-private"
[[ -f "$ssh_config" ]] || { echo 'Missing SSH config' >&2; exit 1; }
for tool in ssh rsync; do command -v "$tool" > /dev/null; done
for directory in app views vendor; do
  [[ -d "$project_root/filolux-private/$directory" ]] || { echo "Missing $directory; install dependencies first" >&2; exit 1; }
done
[[ -f "$project_root/filolux-private/vendor/autoload.php" ]] || { echo 'Missing Composer autoloader' >&2; exit 1; }

# No path is guessed or created as a substitute for an existing installed shop.
printf -v remote_command 'bash -se -- %q %q %q' "$public_path" "$private_path" "$php_override"
ssh -F "$ssh_config" hostinger-filolux "$remote_command" <<'REMOTE'
set -euo pipefail
public_path="$1"
private_path="$2"
php_override="${3:-}"
for tool in rsync realpath; do
  command -v "$tool" > /dev/null || { echo "Missing server command: $tool" >&2; exit 1; }
done
[[ -d "$public_path" && -f "$public_path/index.php" ]] || { echo 'Public destination must be an existing FiloLux site' >&2; exit 1; }
[[ -f "$private_path/config.php" && -f "$private_path/.env" ]] || { echo 'Missing production config.php or .env; verify the deploy path' >&2; exit 1; }
public_real="$(realpath "$public_path")"
private_real="$(realpath "$private_path")"
[[ "$private_real" != "$public_real" && "$private_real" != "$public_real/"* && "$public_real" != "$private_real/"* ]] || { echo 'Public and private directories must be separate' >&2; exit 1; }
for directory in assets; do
  [[ ! -L "$public_path/$directory" ]] || { echo 'Refusing a symlink deployment directory' >&2; exit 1; }
done
for directory in app views vendor; do
  [[ ! -L "$private_path/$directory" ]] || { echo 'Refusing a symlink deployment directory' >&2; exit 1; }
done
# Hostinger's default SSH `php` may differ from the website's PHP.
# Prefer the same 8.2 minor used to build dependencies, then other compatible CLIs.
if [[ -n "$php_override" ]]; then
  [[ "$php_override" == /* ]] || { echo 'HOSTINGER_PHP_BIN must be an absolute executable path' >&2; exit 1; }
  candidates=("$php_override")
else
  candidates=(/opt/alt/php82/usr/bin/php /opt/alt/php83/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php85/usr/bin/php php php8.2 php82 php8.3 php83 php8.4 php84 php8.5 php85)
fi
php_bin=''
for candidate in "${candidates[@]}"; do
  command -v "$candidate" > /dev/null 2>&1 || continue
  if "$candidate" -r '
    if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "PHP " . PHP_VERSION . " is older than 8.2\n"); exit(1); }
    foreach (["pdo_mysql", "curl", "openssl", "fileinfo", "gd", "mbstring"] as $extension) {
      if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: $extension\n"); exit(1); }
    }
  '; then
    php_bin="$candidate"
    break
  fi
  echo "Incompatible PHP CLI: $candidate" >&2
done
if [[ -z "$php_bin" ]]; then
  echo 'No compatible PHP CLI 8.2+ with required extensions found. Check hPanel or set the HOSTINGER_PHP_BIN repository variable to the absolute PHP path.' >&2
  exit 1
fi
printf 'Using PHP CLI: %s (PHP %s)\n' "$php_bin" "$("$php_bin" -r 'echo PHP_VERSION;')"
"$php_bin" -r '
if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "PHP CLI 8.2+ required\n"); exit(1); }
foreach (["pdo_mysql", "curl", "openssl", "fileinfo", "gd", "mbstring"] as $extension) {
    if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: $extension\n"); exit(1); }
}
$config = require $argv[1];
$host = parse_url($config["url"] ?? "", PHP_URL_HOST);
if (!in_array($host, ["filoluxlaser.com", "www.filoluxlaser.com"], true)) {
    fwrite(STDERR, "Destination config does not belong to filoluxlaser.com\n"); exit(1);
}
' "$private_path/config.php"
echo 'Destination verified.'
REMOTE

# Mirror only code directories. Never synchronize the whole website root.
# rsync uses temporary files + rename; persistent data are outside these scopes.
export RSYNC_RSH
printf -v RSYNC_RSH 'ssh -F %q' "$ssh_config"
options=(--recursive --links --times --compress --delay-updates --delete-delay --chmod=D755,F644 --protect-args)
for directory in vendor app views; do
  rsync "${options[@]}" "$project_root/filolux-private/$directory/" "hostinger-filolux:$private_path/$directory/"
done
rsync "${options[@]}" "$project_root/public/assets/" "hostinger-filolux:$public_path/assets/"
# No --delete on either root, on uploads, or on storage/configuration directories.
rsync --times --compress --chmod=F644 --protect-args \
  "$project_root/filolux-private/.htaccess" "$project_root/filolux-private/composer.json" "$project_root/filolux-private/composer.lock" \
  "hostinger-filolux:$private_path/"
rsync --times --compress --chmod=F644 --protect-args \
  "$project_root/public/index.php" "$project_root/public/.htaccess" "hostinger-filolux:$public_path/"
rsync --times --compress --chmod=F644 --protect-args \
  "$project_root/public/uploads/.htaccess" "hostinger-filolux:$public_path/uploads/"
echo 'FiloLux deployed. Production configuration, uploads, storage and database were preserved.'
