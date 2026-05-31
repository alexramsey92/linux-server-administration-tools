#!/usr/bin/env bash
# OEL8 deployment helper for Linux Web Tools
#
# Modes:
#   setup-key  - generate and print an SSH deploy key for GitHub repo access
#   deploy     - full deployment flow (packages, clone/update, app setup, services)

set -euo pipefail

MODE="${1:-deploy}"

# -----------------------------------------------------------------------------
# Runtime configuration (override with environment variables)
# -----------------------------------------------------------------------------
APP_NAME="${APP_NAME:-linux-onprem-webserver-tools}"
APP_DIR="${APP_DIR:-/var/www/${APP_NAME}}"
REPO_SSH="${REPO_SSH:-git@github.com:example/linux-onprem-webserver-tools.git}"
BRANCH="${BRANCH:-main}"
DOMAIN="${DOMAIN:-_}"
APP_USER="${APP_USER:-$USER}"
FPM_USER="${FPM_USER:-nginx}"
FPM_GROUP="${FPM_GROUP:-nginx}"
QUEUE_SERVICE_NAME="${QUEUE_SERVICE_NAME:-linux-onprem-webserver-tools-queue}"
DISABLE_HTTPD="${DISABLE_HTTPD:-true}"
ENABLE_FIREWALL="${ENABLE_FIREWALL:-true}"
# TLS certificate and key paths used by Nginx.
SSL_CERT_FILE="${SSL_CERT_FILE:-/etc/pki/CA/certs/example.com.crt}"
SSL_KEY_FILE="${SSL_KEY_FILE:-/etc/pki/CA/private/example.com.key}"

# Optional short-term server-level auth (Nginx basic auth)
ENABLE_SERVER_AUTH="${ENABLE_SERVER_AUTH:-true}"
BASIC_AUTH_USER="${BASIC_AUTH_USER:-admin}"
BASIC_AUTH_PASSWORD="${BASIC_AUTH_PASSWORD:-}"
BASIC_AUTH_PASSWORD_FILE="${BASIC_AUTH_PASSWORD_FILE:-}"
BASIC_AUTH_REALM="${BASIC_AUTH_REALM:-Restricted}"
BASIC_AUTH_FILE="${BASIC_AUTH_FILE:-/etc/nginx/.htpasswd-${APP_NAME}}"
AUTH_BLOCK=""

# Remove unmanaged PHP binaries in /usr/local/bin if they shadow package-managed PHP.
REMOVE_LOCAL_PHP_BINARIES="${REMOVE_LOCAL_PHP_BINARIES:-true}"
EXPECTED_PHP_VERSION_PREFIX="${EXPECTED_PHP_VERSION_PREFIX:-8.5}"

if [[ "${MODE}" != "setup-key" && "${MODE}" != "deploy" ]]; then
    echo "Usage:"
    echo "  $0 setup-key   # generate deploy SSH key and print public key"
    echo "  $0 deploy      # install and deploy app with Nginx + PHP-FPM"
    exit 1
fi

if [[ "$(id -u)" -eq 0 ]]; then
    SUDO=""
else
    SUDO="sudo"
fi

log() {
    echo "==> $*"
}

warn() {
    echo "WARN: $*"
}

fail() {
    echo "ERROR: $*"
    exit 1
}

# Run a shell command as APP_USER while preserving login shell behavior.
run_as_app_user() {
    ${SUDO} -H -u "${APP_USER}" bash -lc "$1"
}

validate_runtime() {
    log "Deployment parameters"
    echo "     MODE=${MODE}"
    echo "     APP_NAME=${APP_NAME}"
    echo "     APP_DIR=${APP_DIR}"
    echo "     BRANCH=${BRANCH}"
    echo "     DOMAIN=${DOMAIN}"
    echo "     APP_USER=${APP_USER}"
    echo "     FPM_USER=${FPM_USER}"
    echo "     ENABLE_SERVER_AUTH=${ENABLE_SERVER_AUTH}"
}

validate_github_ssh_access() {
    log "Validating GitHub SSH access for ${APP_USER}"

    run_as_app_user 'mkdir -p ~/.ssh && chmod 700 ~/.ssh'
    run_as_app_user 'touch ~/.ssh/known_hosts && chmod 644 ~/.ssh/known_hosts'
    run_as_app_user 'ssh-keyscan github.com >> ~/.ssh/known_hosts 2>/dev/null || true'

    if ! run_as_app_user '[[ -f ~/.ssh/id_ed25519_deploy ]]'; then
        fail "Missing deploy key for ${APP_USER} at ~/.ssh/id_ed25519_deploy. Run: APP_USER=${APP_USER} $0 setup-key"
    fi

    run_as_app_user 'chmod 600 ~/.ssh/id_ed25519_deploy || true'
    run_as_app_user 'chmod 644 ~/.ssh/id_ed25519_deploy.pub || true'
    run_as_app_user 'touch ~/.ssh/config && chmod 600 ~/.ssh/config'
    run_as_app_user 'grep -q "Host github.com" ~/.ssh/config || cat >> ~/.ssh/config <<EOF
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/id_ed25519_deploy
  IdentitiesOnly yes
EOF'

    if run_as_app_user 'ssh -i ~/.ssh/id_ed25519_deploy -o IdentitiesOnly=yes -o BatchMode=yes -T git@github.com > /tmp/github-ssh-check.log 2>&1'; then
        log "GitHub SSH validation passed"

        return
    fi

    if run_as_app_user 'grep -qi "successfully authenticated" /tmp/github-ssh-check.log'; then
        log "GitHub SSH validation passed"

        return
    fi

    warn "GitHub SSH validation failed. Diagnostic output follows:"
    run_as_app_user 'cat /tmp/github-ssh-check.log || true'
    fail "Unable to authenticate to GitHub as ${APP_USER}. Confirm the deploy key is present and added to the correct repository deploy keys."
}

# Create deploy key and minimal SSH config for cloning private repos from GitHub.
setup_deploy_key() {
    log "Generating deploy key for ${APP_USER}"
    run_as_app_user 'mkdir -p ~/.ssh && chmod 700 ~/.ssh'

    if run_as_app_user '[[ -f ~/.ssh/id_ed25519_deploy ]]'; then
        echo "Deploy key already exists at ~/.ssh/id_ed25519_deploy"
    else
        run_as_app_user 'ssh-keygen -t ed25519 -C "oel8-deploy" -f ~/.ssh/id_ed25519_deploy -N ""'
        echo "Created ~/.ssh/id_ed25519_deploy"
    fi

    run_as_app_user 'touch ~/.ssh/config && chmod 600 ~/.ssh/config'
    run_as_app_user 'grep -q "Host github.com" ~/.ssh/config || cat >> ~/.ssh/config <<EOF
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/id_ed25519_deploy
  IdentitiesOnly yes
EOF'

    echo
    echo "==> Add this public key to GitHub repo Deploy Keys (read-only)"
    run_as_app_user 'cat ~/.ssh/id_ed25519_deploy.pub'
    echo
    echo "After adding key in GitHub, test with:"
    echo "  sudo -u ${APP_USER} ssh -T git@github.com"
}

# Install OS packages, PHP 8.5 (Remi), Nginx, Node, and Composer.
remove_existing_php_stack() {
    log "Removing existing PHP packages and binaries if present"

    ${SUDO} systemctl disable --now php-fpm 2>/dev/null || true

    mapfile -t existing_php_packages < <(rpm -qa | grep -E '^(php|php[0-9]|legacy-php)' | sort || true)

    if [[ ${#existing_php_packages[@]} -gt 0 ]]; then
        echo "     Existing PHP packages detected:"
        printf '       - %s\n' "${existing_php_packages[@]}"
        ${SUDO} dnf -y remove "${existing_php_packages[@]}" || true
    else
        log "No existing PHP RPM packages detected"
    fi

    if [[ "${REMOVE_LOCAL_PHP_BINARIES}" == "true" ]]; then
        for php_binary in /usr/local/bin/php /usr/local/bin/phpize /usr/local/bin/php-config /usr/local/bin/pear /usr/local/bin/pecl; do
            if [[ -e "${php_binary}" ]]; then
                if rpm -qf "${php_binary}" >/dev/null 2>&1; then
                    continue
                fi

                warn "Found unmanaged PHP binary in path: ${php_binary}"
                ${SUDO} mv "${php_binary}" "${php_binary}.bak.$(date +%Y%m%d%H%M%S)"
            fi
        done
    fi

    hash -r 2>/dev/null || true
}

verify_php_installation() {
    log "Verifying active PHP installation"

    local active_php_path
    active_php_path="$(command -v php)"

    if [[ -z "${active_php_path}" ]]; then
        fail "No php binary found in PATH after installation"
    fi

    local cli_version
    cli_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"

    local fpm_version
    fpm_version="$(php-fpm -i 2>/dev/null | awk -F'=> ' '/^PHP Version/ {print $2; exit}' || true)"

    echo "     which -a php:"
    which -a php || true
    echo "     active php: ${active_php_path}"
    echo "     cli php version: ${cli_version:-unknown}"
    echo "     fpm php version: ${fpm_version:-unknown}"

    echo "     installed php packages:"
    rpm -qa | grep -E '^php(|[0-9].*)-' | sort || true

    if rpm -qa | grep -q '^legacy-php'; then
        fail "Legacy PHP package is still installed. Remove it before continuing."
    fi

    if [[ "${cli_version}" != "${EXPECTED_PHP_VERSION_PREFIX}" ]]; then
        fail "Active CLI PHP version is ${cli_version:-unknown}, expected ${EXPECTED_PHP_VERSION_PREFIX}. Check PATH for stale binaries or package conflicts."
    fi

    if [[ -n "${fpm_version}" && "${fpm_version}" != ${EXPECTED_PHP_VERSION_PREFIX}* ]]; then
        fail "Active PHP-FPM version is ${fpm_version}, expected ${EXPECTED_PHP_VERSION_PREFIX}."
    fi

    log "PHP ${EXPECTED_PHP_VERSION_PREFIX} is active for CLI and PHP-FPM"
}

install_packages() {
    log "Installing system packages"
    ${SUDO} dnf -y update
    ${SUDO} dnf -y install git unzip curl policycoreutils-python-utils firewalld

    remove_existing_php_stack

    ${SUDO} dnf -y install https://rpms.remirepo.net/enterprise/remi-release-8.rpm || true
    ${SUDO} dnf module -y reset php
    ${SUDO} dnf module -y enable php:remi-8.5
    ${SUDO} dnf -y install php php-cli php-fpm php-mbstring php-xml php-pdo php-sqlite3 php-json php-opcache php-bcmath php-gd php-intl php-curl

    ${SUDO} dnf -y install nginx nodejs npm

    if ! command -v composer >/dev/null 2>&1; then
        log "Installing Composer"
        cd /tmp
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ${SUDO} php composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm -f composer-setup.php
    else
        log "Composer already installed at $(command -v composer)"
    fi

    hash -r 2>/dev/null || true
    verify_php_installation
}

# Clone repository on first run, otherwise fetch/reset/pull target branch.
clone_or_update_repo() {
    log "Cloning/updating repository"
    validate_github_ssh_access
    ${SUDO} mkdir -p "${APP_DIR}"
    ${SUDO} chown -R "${APP_USER}:${APP_USER}" "${APP_DIR}"

    if run_as_app_user "[[ -d '${APP_DIR}/.git' ]]"; then
        log "Existing git repository found in ${APP_DIR}; updating branch ${BRANCH}"
        run_as_app_user "cd '${APP_DIR}' && git fetch origin && git checkout '${BRANCH}' && git pull --ff-only origin '${BRANCH}'"
    else
        log "No git repository found in ${APP_DIR}; cloning ${REPO_SSH}"
        run_as_app_user "git clone '${REPO_SSH}' '${APP_DIR}'"
        run_as_app_user "cd '${APP_DIR}' && git checkout '${BRANCH}'"
    fi
}

# Install app dependencies, build frontend, run migrations, and cache config/routes/views.
configure_app() {
    log "Installing app dependencies and building assets"
    if ! run_as_app_user "[[ -f '${APP_DIR}/.env' ]]"; then
        log "No .env file detected; copying from .env.example"
        run_as_app_user "cp '${APP_DIR}/.env.example' '${APP_DIR}/.env'"
    fi

    run_as_app_user "cd '${APP_DIR}' && composer install --no-dev --optimize-autoloader"
    run_as_app_user "cd '${APP_DIR}' && npm install"
    run_as_app_user "cd '${APP_DIR}' && npm run build"

    if run_as_app_user "grep -q '^APP_KEY=$' '${APP_DIR}/.env' || ! grep -q '^APP_KEY=' '${APP_DIR}/.env'"; then
        run_as_app_user "cd '${APP_DIR}' && php artisan key:generate --force"
    fi

    run_as_app_user "cd '${APP_DIR}' && php artisan migrate --force"
    run_as_app_user "cd '${APP_DIR}' && php artisan config:cache"
    run_as_app_user "cd '${APP_DIR}' && php artisan route:cache"
    run_as_app_user "cd '${APP_DIR}' && php artisan view:cache"

    log "Setting file permissions"
    ${SUDO} chown -R "${APP_USER}:${FPM_GROUP}" "${APP_DIR}"
    ${SUDO} find "${APP_DIR}" -type f -exec chmod 644 {} \;
    ${SUDO} find "${APP_DIR}" -type d -exec chmod 755 {} \;
    ${SUDO} chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
}

# Configure PHP-FPM to run under nginx user/group and use Unix socket.
configure_php_fpm() {
    log "Configuring PHP-FPM"
    ${SUDO} sed -i "s/^user = .*/user = ${FPM_USER}/" /etc/php-fpm.d/www.conf
    ${SUDO} sed -i "s/^group = .*/group = ${FPM_GROUP}/" /etc/php-fpm.d/www.conf
    ${SUDO} sed -i "s|^listen = .*|listen = /run/php-fpm/www.sock|" /etc/php-fpm.d/www.conf
    ${SUDO} sed -i "s/^;*listen.owner = .*/listen.owner = ${FPM_USER}/" /etc/php-fpm.d/www.conf
    ${SUDO} sed -i "s/^;*listen.group = .*/listen.group = ${FPM_GROUP}/" /etc/php-fpm.d/www.conf

    ${SUDO} systemctl enable --now php-fpm
}

# Configure optional Nginx basic auth with either:
# - BASIC_AUTH_PASSWORD_FILE (preferred for automation), or
# - BASIC_AUTH_PASSWORD
# If neither password source is provided, an existing BASIC_AUTH_FILE is reused.
configure_server_auth() {
    if [[ "${ENABLE_SERVER_AUTH}" != "true" ]]; then
        log "Server auth disabled"
        AUTH_BLOCK=""

        return
    fi

    log "Configuring server-level basic auth"

    local auth_password=""

    if [[ -n "${BASIC_AUTH_PASSWORD_FILE}" ]]; then
        if [[ -f "${BASIC_AUTH_PASSWORD_FILE}" ]]; then
            auth_password="$(<"${BASIC_AUTH_PASSWORD_FILE}")"
        else
            fail "BASIC_AUTH_PASSWORD_FILE does not exist: ${BASIC_AUTH_PASSWORD_FILE}"
        fi
    elif [[ -n "${BASIC_AUTH_PASSWORD}" ]]; then
        auth_password="${BASIC_AUTH_PASSWORD}"
    fi

    if [[ -z "${auth_password}" ]]; then
        if [[ ! -f "${BASIC_AUTH_FILE}" ]]; then
            fail "Basic auth enabled but no password provided and auth file missing. Set BASIC_AUTH_PASSWORD or BASIC_AUTH_PASSWORD_FILE for first-time setup."
        fi

        log "Reusing existing auth file at ${BASIC_AUTH_FILE}"
        ${SUDO} chown root:nginx "${BASIC_AUTH_FILE}"
        ${SUDO} chmod 640 "${BASIC_AUTH_FILE}"
    else
        local password_hash
        password_hash="$(openssl passwd -apr1 "${auth_password}")"

        ${SUDO} mkdir -p "$(dirname "${BASIC_AUTH_FILE}")"
        ${SUDO} bash -c "printf '%s:%s\n' '${BASIC_AUTH_USER}' '${password_hash}' > '${BASIC_AUTH_FILE}'"
        ${SUDO} chown root:nginx "${BASIC_AUTH_FILE}"
        ${SUDO} chmod 640 "${BASIC_AUTH_FILE}"
    fi

    AUTH_BLOCK=$(cat <<EOF
    auth_basic "${BASIC_AUTH_REALM}";
    auth_basic_user_file ${BASIC_AUTH_FILE};
EOF
)
}

# Write and activate Nginx virtual host with HTTP->HTTPS redirect and Laravel routing.
configure_nginx() {
    log "Configuring Nginx"

    if [[ ! -f "${SSL_CERT_FILE}" ]]; then
        fail "SSL certificate not found at ${SSL_CERT_FILE}"
    fi

    if [[ ! -f "${SSL_KEY_FILE}" ]]; then
        fail "SSL private key not found at ${SSL_KEY_FILE}"
    fi

    ${SUDO} tee "/etc/nginx/conf.d/${APP_NAME}.conf" >/dev/null <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN};

${AUTH_BLOCK}

    ssl_certificate ${SSL_CERT_FILE};
    ssl_certificate_key ${SSL_KEY_FILE};
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:10m;
    ssl_protocols TLSv1.2 TLSv1.3;

    root ${APP_DIR}/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    ${SUDO} nginx -t
    ${SUDO} systemctl enable --now nginx
    ${SUDO} systemctl reload nginx
}

# Create and enable a systemd service for Laravel queue worker.
configure_queue_worker() {
    log "Configuring queue worker service"
    ${SUDO} tee "/etc/systemd/system/${QUEUE_SERVICE_NAME}.service" >/dev/null <<EOF
[Unit]
Description=Linux On-Prem Webserver Tools Laravel Queue Worker
After=network.target

[Service]
User=${FPM_USER}
Group=${FPM_GROUP}
Restart=always
RestartSec=5
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/log/${QUEUE_SERVICE_NAME}.log
StandardError=append:/var/log/${QUEUE_SERVICE_NAME}-error.log

[Install]
WantedBy=multi-user.target
EOF

    ${SUDO} systemctl daemon-reload
    ${SUDO} systemctl enable --now "${QUEUE_SERVICE_NAME}"
}

# Configure host firewall and SELinux labels/booleans required by Laravel.
configure_firewall_selinux() {
    if [[ "${ENABLE_FIREWALL}" == "true" ]]; then
        log "Configuring firewall"
        ${SUDO} systemctl enable --now firewalld
        ${SUDO} firewall-cmd --permanent --add-service=http
        ${SUDO} firewall-cmd --permanent --add-service=https
        ${SUDO} firewall-cmd --reload
    fi

    log "Configuring SELinux for Laravel writable paths"
    ${SUDO} setsebool -P httpd_can_network_connect 1
    ${SUDO} chcon -R -t httpd_sys_rw_content_t "${APP_DIR}/storage"
    ${SUDO} chcon -R -t httpd_sys_rw_content_t "${APP_DIR}/bootstrap/cache"
}

# Optionally disable/remove Apache stack and stop Puppet services.
disable_httpd_if_requested() {
    if [[ "${DISABLE_HTTPD}" == "true" ]]; then
        log "Disabling Apache httpd"
        if ${SUDO} systemctl is-enabled httpd >/dev/null 2>&1 || ${SUDO} systemctl is-active httpd >/dev/null 2>&1; then
            ${SUDO} systemctl disable --now httpd || true
        fi

        log "Removing Apache and legacy PHP package"
        ${SUDO} dnf -y remove httpd legacy-php || true
    fi

    log "Disabling Puppet agent"
    if ${SUDO} systemctl list-unit-files | grep -q '^puppet\.service'; then
        ${SUDO} systemctl disable --now puppet || true
    fi

    if ${SUDO} systemctl list-unit-files | grep -q '^puppet-agent\.service'; then
        ${SUDO} systemctl disable --now puppet-agent || true
    fi
}

if [[ "${MODE}" == "setup-key" ]]; then
    setup_deploy_key
    exit 0
fi

# -----------------------------------------------------------------------------
# Deploy sequence
# -----------------------------------------------------------------------------
validate_runtime
install_packages
clone_or_update_repo
configure_app
configure_php_fpm
disable_httpd_if_requested
configure_server_auth
configure_nginx
configure_queue_worker
configure_firewall_selinux

echo
echo "Deployment complete."
echo "App path: ${APP_DIR}"
echo "Nginx server_name: ${DOMAIN}"
echo "Queue service: ${QUEUE_SERVICE_NAME}"
