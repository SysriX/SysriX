#!/bin/bash
#name=SysriX-Vorlagen
#description=Holt die Unraid-Vorlagen aller SysriX-Repositories (öffentlich + privat) nach Apps › Private und Docker › Container hinzufügen. Fehlende werden angelegt, vorhandene aktualisiert – eigene Werte bleiben.
#argumentDescription=Optional: --probelauf (nur anzeigen) oder --token=… (GitHub-Token speichern)
#argumentDefault=
#
# User Script für das Plugin „User Scripts“. Installieren (einmal in der Unraid-Konsole):
#   mkdir -p /boot/config/plugins/user.scripts/scripts/sysrix-vorlagen && curl -fsSL https://raw.githubusercontent.com/SysriX/SysriX/main/unraid/vorlagen.sh -o /boot/config/plugins/user.scripts/scripts/sysrix-vorlagen/script && bash /boot/config/plugins/user.scripts/scripts/sysrix-vorlagen/script
# Danach unter Einstellungen › User Scripts: „Run Script“ zum Aktualisieren, Zeitplan nach Wunsch (z. B. Daily).
# Holt bei jedem Lauf die neueste Fassung von sysrix-vorlagen.php (fällt sonst auf die gespeicherte zurück).

D=/boot/config/plugins/sysrix-vorlagen
URL=https://raw.githubusercontent.com/SysriX/SysriX/main/unraid/sysrix-vorlagen.php
US=/boot/config/plugins/user.scripts/scripts/sysrix-vorlagen

mkdir -p "$D"
if curl -fsSL --max-time 60 "$URL" -o "$D/sysrix-vorlagen.php.neu" && php -l "$D/sysrix-vorlagen.php.neu" >/dev/null 2>&1; then
  mv -f "$D/sysrix-vorlagen.php.neu" "$D/sysrix-vorlagen.php"
else
  rm -f "$D/sysrix-vorlagen.php.neu"
  echo "Neueste Fassung nicht abrufbar – nehme die gespeicherte."
fi
[ -f "$D/sysrix-vorlagen.php" ] || { echo "sysrix-vorlagen.php fehlt – Netz prüfen."; exit 1; }

# Beschreibung für die User-Scripts-Liste (falls von Hand installiert)
if [ -d "$US" ] && [ ! -s "$US/description" ]; then
  echo "Holt die Unraid-Vorlagen aller SysriX-Repositories nach Apps › Private und Docker › Container hinzufügen (eigene Werte bleiben)." > "$US/description"
fi

php "$D/sysrix-vorlagen.php" "$@"
