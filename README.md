# VelaLara

A Laravel 13 administrator-facing control panel prototype for a dedicated **Ubuntu Server 24.04 LTS** machine, Nginx, PHP 8.3 FPM and MariaDB. Laravel no longer designates current releases as LTS; 13 is the current supported major. The Ubuntu release is LTS. This is a single-server, single-administrator-trust-domain design, not shared-hosting isolation.

**Not production-ready.** This implementation has not undergone an independent security review or end-to-end testing on Ubuntu, has no MFA, audit trail, quota enforcement, resource cleanup/reconciliation, robust failure rollback, backups or disaster recovery, and does not isolate hostile workloads. Do not expose it publicly or host mutually untrusted users on it. Use a private management network/VPN and independent OS/container/VM isolation. Hosting accounts are Linux service identities, not panel login accounts; every panel user is an administrator with full control of every account. Treat compromise of the panel/web-server account as a serious server compromise. Do not run this on an existing server with valuable configuration without a tested backup and a staging rehearsal.

## What it does

* Creates locked, non-interactive Linux users with generated names, private homes, `public_html`, ACL access for Nginx, and per-user PHP-FPM pools. Password login is intentionally disabled; no `passwd` endpoint exists. This reduces the attack surface.
* Creates HTTP Nginx virtual hosts, dedicated PHP sockets, and Let's Encrypt certificates using the HTTP-01 webroot challenge. Certbot's systemd timer renews certificates; a deploy hook tests and reloads Nginx. Hosted HTTP traffic is not automatically redirected to HTTPS.
* Creates a MariaDB database and a randomly named localhost-only user with privileges only on that database. Credentials appear once in an encrypted flash session, and are not saved in the panel database. Securely copy them immediately.
* Lists, downloads, uploads, writes and deletes files under an account's `public_html`. Paths are relative, traversal and symlink traversal are rejected, and file operations drop to the target UID/GID. File size is limited to 256 KiB. File writes replace the named file; directories can be browsed, but not created or deleted through the UI.
* Records basic provisioning status. Failed or interrupted operations may leave OS resources behind. Never blindly retry; reconcile as described below.

## Privilege model and limitations

`SystemCommandService` sends bounded JSON over stdin using Laravel's `Process` facade and an argument array. The root-owned `/usr/local/sbin/velalara-helper` accepts a small allowlist of typed operations, uses fixed absolute executables and no shell, validates identifiers again, serializes provisioning, and drops privileges before file operations. The sole sudo rule permits executing **only that helper with no arguments**; it does not grant unrestricted `useradd`, `systemctl`, `certbot`, or shell access. Directly granting those tools as root to `www-data` would be unsafe because their arguments can often be abused for root execution or arbitrary file access. The helper nevertheless has significant power: a compromised web application can create accounts/databases/sites and request certificates. It is not an authorization boundary between panel users. Keep its code and all parent directories root-owned and unwritable by `www-data`; do not add `SETENV` or preserve Python environment variables in sudo policy.

Unix UIDs, permissions, ACLs and FPM pools are **not a sandbox**. Nginx denies symlinks in hosted sites, and Linux `fs.protected_hardlinks=1` must remain enabled. Site owners can alter permissions of their own files; PHP and native binaries share the same kernel and may have access to other world-readable host resources. Public web files are intentionally exposed by Nginx. There are no cgroups, per-user quotas, outbound network restrictions, process limits beyond the pool, or protection from local privilege escalation. Use separate VMs for adversarial tenants. Never give hosted code access to the panel FPM socket, `.env`, panel database, helper, or sudo policy.

## Installation (fresh dedicated Ubuntu 24.04)

The following assumes a root-capable operator, a public IP with ports 80/443 reachable, a DNS A/AAAA record for the panel name already pointing to this machine, and outbound access for packages and Let's Encrypt. If an AAAA record exists, IPv6 must work. Put management access behind a VPN or equivalent network ACL. Do not use `artisan serve` in production. Review all commands before running them.

1. Install prerequisites and obtain Composer using the [official verified installer](https://getcomposer.org/download/). PHP, Composer and Git must be available in the operator's shell.

```bash
sudo apt update
sudo apt install -y git nginx mariadb-server certbot php8.3-cli php8.3-fpm php8.3-mbstring php8.3-xml php8.3-curl php8.3-sqlite3 php8.3-mysql php8.3-zip unzip acl sudo python3
sudo systemctl enable --now nginx mariadb php8.3-fpm certbot.timer
```

2. Check out this repository at `/opt/velalara`, then install PHP dependencies as your normal user (do not run Composer as root). The repository currently does not ship a Composer lock file; generate and review one in a controlled build environment before a production deployment.

```bash
sudo mkdir -p /opt/velalara
sudo chown "$(id -un)":"$(id -gn)" /opt/velalara
# Copy or clone the reviewed VelaLara repository contents into /opt/velalara.
cd /opt/velalara
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

3. Prepare a private SQLite database for panel state, the ACME webroot and the helper's root-only account registry. Use separate MariaDB for hosted databases; the helper authenticates as MariaDB root using the local Unix socket. Ensure this authentication works for OS root (`sudo mariadb --protocol=socket -e 'SELECT 1'`).

```bash
sudo install -d -o root -g root -m 0755 /var/lib/velalara /var/lib/velalara/acme
sudo install -d -o root -g root -m 0700 /var/lib/velalara/accounts
sudo install -d -o www-data -g www-data -m 0700 /var/lib/velalara/panel
sudo -u www-data touch /var/lib/velalara/panel/panel.sqlite
```

Edit `.env`: set `APP_URL` to the panel's HTTPS URL, `DB_CONNECTION=sqlite`, `DB_DATABASE=/var/lib/velalara/panel/panel.sqlite`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, and `SESSION_SECURE_COOKIE=true`. Never publish `.env` or the application directory. Prepare the runtime permissions, run migrations, and create the first administrator using an interactive password prompt:

```bash
sudo chown root:www-data .env
sudo chmod 0640 .env
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R u=rwX,go= storage bootstrap/cache
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan panel:admin your-admin-email@example.com
```

Replace the email with your own address. Set the final ownership and permissions after these commands:

```bash
sudo chown -R root:root /opt/velalara
sudo chown root:www-data /opt/velalara/.env
sudo chmod 0640 /opt/velalara/.env
sudo chown -R www-data:www-data /opt/velalara/storage /opt/velalara/bootstrap/cache
sudo chmod -R u=rwX,go= /opt/velalara/storage /opt/velalara/bootstrap/cache
```

The web server may write only `storage`, `bootstrap/cache`, and the panel database, not application code or the helper. Take care not to make these writable locations executable code paths. Production deployments should build dependencies in a trusted environment and protect the resulting artifact.

4. Install and verify the privileged interface and renewal hook:

```bash
sudo install -o root -g root -m 0755 deploy/velalara-helper /usr/local/sbin/velalara-helper
sudo install -o root -g root -m 0440 deploy/controlpanel.sudoers /etc/sudoers.d/controlpanel
sudo visudo -cf /etc/sudoers.d/controlpanel
sudo install -o root -g root -m 0755 deploy/velalara-renew-hook /etc/letsencrypt/renewal-hooks/deploy/velalara-nginx
```

The helper is deliberately pinned to PHP 8.3 paths. Do not rename the FPM service/socket without updating and reviewing the helper, pool paths and panel Nginx template. Do not expose the helper as an HTTP endpoint or let tenants write its script, Python libraries, or `/usr/local/sbin`.

5. Configure the panel certificate and Nginx. The next commands prompt for your real DNS name and certificate email. They install a temporary HTTP challenge site, request a certificate, then replace it with the HTTPS configuration. Review the generated config and restrict panel access to your VPN/network at the firewall or in Nginx. Never use a hosted-domain name for the panel.

```bash
read -r -p 'Panel DNS name: ' PANEL_DOMAIN
read -r -p 'Certificate email: ' PANEL_EMAIL
# The DNS name is interpolated into Nginx configuration, so reject metacharacters.
[[ "$PANEL_DOMAIN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ ]] || { echo 'Invalid DNS name'; exit 1; }
sudo rm -f /etc/nginx/sites-enabled/default
printf 'server { listen 80; listen [::]:80; server_name %s; location ^~ /.well-known/acme-challenge/ { root /var/lib/velalara/acme; } location / { return 404; } }\n' "$PANEL_DOMAIN" | sudo tee /etc/nginx/sites-available/velalara-panel >/dev/null
sudo ln -s /etc/nginx/sites-available/velalara-panel /etc/nginx/sites-enabled/velalara-panel
sudo nginx -t && sudo systemctl reload nginx
sudo certbot certonly --webroot -w /var/lib/velalara/acme -d "$PANEL_DOMAIN" --email "$PANEL_EMAIL" --agree-tos --non-interactive
sed "s/panel.example.com/$PANEL_DOMAIN/g" deploy/nginx-panel.conf | sudo tee /etc/nginx/sites-available/velalara-panel >/dev/null
sudo nginx -t && sudo systemctl reload nginx
sudo certbot renew --dry-run
```

Log in at your panel HTTPS URL. Create an account, then a domain. Point the domain's DNS to this server, allow port 80, and issue its certificate using the dashboard. Verify `systemctl status certbot.timer` and monitor renewal failures. Back up the panel database, `/etc/letsencrypt`, hosted files and databases with a tested restore procedure.

## Operations and recovery

Provisioning changes multiple independent systems and cannot be transactional. A failure after `useradd`, pool creation, MariaDB statements or certificate issuance can leave partial resources. Failed accounts/domains remain in the panel as `failed`; stop and inspect the journal (`journalctl -t velalara-helper` or system journal), `/etc/php/8.3/fpm/pool.d`, `/etc/nginx/sites-{available,enabled}`, `getent passwd`, `/var/lib/velalara/accounts`, MariaDB users/databases and `/var/log/letsencrypt/letsencrypt.log`. System-command errors are intentionally generic to avoid leaking SQL credentials. Manually reconcile on a backup/staging system before modifying live state; no automated destructive cleanup is provided. A database can be created but not recorded if the panel database write fails. Backups and reconciliation are mandatory.

The panel's admin credentials grant complete access. No self-registration is available. Additional admins can be created with `panel:admin`; removal and password reset must be performed by a trusted operator using Laravel/SQLite. Rate limiting is per IP and is not a substitute for MFA, VPN, or monitoring. Session records and logs may contain sensitive metadata; protect and rotate them. A lost database password cannot be retrieved from VelaLara; reset it directly in MariaDB.

## Tests

```bash
python3 -m unittest discover -s tests/helper -v
composer install
php artisan test
sudo visudo -cf deploy/controlpanel.sudoers
```

The tests mock privileged operations; they do **not** exercise real `useradd`, Nginx, FPM, MariaDB, Certbot or systemd. Before considering deployment, run a full integration/security review on an expendable Ubuntu 24.04 VM, including negative path/symlink and concurrent request tests, certificate issuance/renewal, failure injection, privilege boundary tests, and recovery drills.
