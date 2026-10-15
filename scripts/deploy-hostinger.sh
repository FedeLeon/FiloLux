#!/usr/bin/env bash
# Run by GitHub Actions after Composer installation. No credentials in source.
set -euo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
public_path="${HOSTINGER_DEPLOY_PATH:?Set HOSTINGER_DEPLOY_PATH to the absolute Hostinger public_html path}"
ssh_config="${FILOLUX_SSH_CONFIG:-$HOME/.ssh/filolux_config}"

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
printf -v remote_command 'bash -se -- %q %q' "$public_path" "$private_path"
ssh -F "$ssh_config" hostinger-filolux "$remote_command" <<'REMOTE'
set -euo pipefail
public_path="$1"
private_path="$2"
for tool in php rsync realpath; do command -v "$tool" > /dev/null; done
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
php -r '
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
