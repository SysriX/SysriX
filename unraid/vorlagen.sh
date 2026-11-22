#!/bin/bash
# Auf Unraid ausführen (Konsole oder User Scripts). Lädt sysrix-vorlagen.php und startet es:
# holt die Vorlagen aller SysriX-Repositories (öffentlich + privat) nach Community Applications (Bereich „Private“)
# und als Benutzer-Vorlagen (Docker → Container hinzufügen). Vorhandene werden aktualisiert, eigene Werte bleiben.
#   curl -fsSL https://raw.githubusercontent.com/SysriX/SysriX/main/unraid/vorlagen.sh | bash -s -- --einrichten
# Weitere Optionen: --probelauf, --token=XYZ, --still (Beschreibung im Kopf von sysrix-vorlagen.php)
set -euo pipefail
D=/boot/config/plugins/sysrix-vorlagen
mkdir -p "$D"
curl -fsSL --max-time 60 https://raw.githubusercontent.com/SysriX/SysriX/main/unraid/sysrix-vorlagen.php -o "$D/sysrix-vorlagen.php.neu"
php -l "$D/sysrix-vorlagen.php.neu" >/dev/null
mv -f "$D/sysrix-vorlagen.php.neu" "$D/sysrix-vorlagen.php"
exec php "$D/sysrix-vorlagen.php" "$@"
