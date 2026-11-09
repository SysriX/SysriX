## Unraid-Pakete

Docker-Container für **Unraid**: installierbar über Community Applications und automatisch aktuell gehalten. Jedes Paket hat ein eigenes Repository mit Quellcode, Anleitung und Unraid-Vorlage.

| | Paket | Was es macht |
| --- | --- | --- |
| <img src="https://raw.githubusercontent.com/SysriX/haproxy_unraid/main/icon.svg" width="40"> | [**haproxy-unraid**](https://github.com/SysriX/haproxy_unraid) | Reverse-Proxy für außen und innen: verteilt Anfragen nach Name an die Dienste, beendet TLS oder reicht verschlüsselt durch, wehrt Scanner und Passwort-Raten ab. Änderungen werden geprüft übernommen und springen bei Fehlern zurück. |
| <img src="https://raw.githubusercontent.com/SysriX/haproxy-ui_unraid/main/icon.svg" width="40"> | [**haproxy-ui**](https://github.com/SysriX/haproxy-ui_unraid) | Weboberfläche für haproxy-unraid: Dienste und Dateien bearbeiten, mit einem Klick prüfen und übernehmen, Live-Statistik und Zertifikats-Laufzeiten. Optional Meldung an Home Assistant per MQTT. |
| <img src="https://raw.githubusercontent.com/SysriX/certwarden_unraid/main/icon.svg" width="40"> | [**certwarden-unraid**](https://github.com/SysriX/certwarden_unraid) | Let's-Encrypt-Zertifikate mit Weboberfläche (Cert Warden), automatisch erneuert. Legt sie auf eine Unraid-Freigabe, damit andere Container sie nutzen können; STRATO und Hetzner per DNS-01. |

### Zusammenspiel

certwarden-unraid und haproxy-unraid laufen jeweils allein; haproxy-ui nur zusammen mit haproxy-unraid.

```
          Internet / LAN
                │ 80/443
        ┌───────▼────────┐  Aufträge   ┌──────────────┐
        │ haproxy-unraid │◄────────────│  haproxy-ui  │──MQTT──► Home Assistant
        └───────▲────────┘  Statistik  └──────────────┘
                │ Zertifikate (API)
       ┌────────┴──────────┐
       │ certwarden-unraid │──► Freigabe „zertifikate“ ──► andere Container
       └───────────────────┘
```

### Mindestvoraussetzungen

- Unraid mit Docker und dem Plugin **Community Applications**
- ein benutzerdefiniertes Docker-Netz (z. B. `br0` oder ein VLAN) und je Container eine **freie feste IP**
- Prozessor **x86-64** (amd64)

| Paket | braucht zusätzlich | optional |
| --- | --- | --- |
| **haproxy-unraid** | ein Statistik-Passwort; für Zugriff von außen: Weiterleitung von Port 80/443 in der Firewall auf die IP von HAProxy | certwarden-unraid für automatische Zertifikate (sonst eigene Zertifikate), Syslog-Empfänger für Zugriffslogs |
| **haproxy-ui** | **haproxy-unraid** läuft, mit demselben appdata-Ordner; IP von haproxy-ui in dessen Liste interner Netze | MQTT-Broker und Home Assistant |
| **certwarden-unraid** | eigene Domain; entweder DNS bei STRATO/Hetzner (DNS-01) oder Port 80 von außen erreichbar (HTTP-01, z. B. über haproxy-unraid) | Unraid-Freigabe für die Ablage der Zertifikate |

### Installieren

Bis die Pakete offiziell in Community Applications stehen: alle drei Vorlagen auf einmal holen (Unraid-Konsole) …

```bash
Z=/boot/config/plugins/community.applications/private/sysrix; mkdir -p $Z; for p in haproxy_unraid:haproxy-unraid haproxy-ui_unraid:haproxy-ui certwarden_unraid:certwarden-unraid; do curl -fsSL https://raw.githubusercontent.com/SysriX/${p%%:*}/main/templates/${p##*:}.xml -o $Z/${p##*:}.xml && echo "${p##*:} ok"; done
```

… oder nur eine einzelne – der Befehl steht in der Anleitung des jeweiligen Pakets. Danach in Unraid unter **Apps › Private** installieren und die Felder ausfüllen.

### Updates

Jedes Paket baut auf einem offiziellen Image auf und wird automatisch neu gebaut, sobald es dort ein Update gibt. Unraid zeigt dann „Update verfügbar“.

### Hilfe

Fragen und Fehler bitte als GitHub-Issue im Repository des jeweiligen Pakets.
