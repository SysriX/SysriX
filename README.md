<p align="center">
  <img src="assets/banner.svg" alt="SysriX – Selbst gehostet, sauber verpackt" width="100%">
</p>

<p align="center">
  <a href="#unraid-pakete"><img src="https://img.shields.io/badge/Unraid-4_Pakete-3ddc84?style=for-the-badge&labelColor=1f2a37" alt="Unraid: 4 Pakete"></a>
  <a href="#home-assistant"><img src="https://img.shields.io/badge/Home_Assistant-in_Arbeit-64b5f6?style=for-the-badge&labelColor=1f2a37" alt="Home Assistant: in Arbeit"></a>
  <img src="https://img.shields.io/badge/Lizenz-MIT-9fb3c8?style=for-the-badge&labelColor=1f2a37" alt="Lizenz MIT">
</p>

<p align="center">
  Eigene Bausteine fürs selbst gehostete Zuhause – jedes Projekt in einem eigenen Repository<br>
  mit Quellcode, Anleitung und automatischen Updates. Hier ist das Inhaltsverzeichnis.
</p>

<br>

<a id="unraid-pakete"></a>
<img src="assets/abschnitt-unraid.svg" alt="Unraid-Pakete" width="100%">

<table>
  <tr>
    <td width="33%" valign="top">
      <a href="https://github.com/SysriX/haproxy_unraid"><img src="https://raw.githubusercontent.com/SysriX/haproxy_unraid/main/icon.svg" width="56" align="left" alt=""></a>
      <b><a href="https://github.com/SysriX/haproxy_unraid">haproxy-unraid</a></b><br>
      <sub>Reverse-Proxy</sub>
      <br clear="left"><br>
      Eingang für außen und innen: verteilt nach Name, beendet TLS oder reicht durch, wehrt Scanner ab. Änderungen werden geprüft und springen bei Fehlern zurück.
      <br><br>
      <a href="https://github.com/SysriX/haproxy_unraid/actions/workflows/image.yml"><img src="https://github.com/SysriX/haproxy_unraid/actions/workflows/image.yml/badge.svg" alt="Build"></a>
    </td>
    <td width="33%" valign="top">
      <a href="https://github.com/SysriX/haproxy-ui_unraid"><img src="https://raw.githubusercontent.com/SysriX/haproxy-ui_unraid/main/icon.svg" width="56" align="left" alt=""></a>
      <b><a href="https://github.com/SysriX/haproxy-ui_unraid">haproxy-ui</a></b><br>
      <sub>Weboberfläche</sub>
      <br clear="left"><br>
      HAProxy im Browser pflegen: Dienste und Dateien bearbeiten, mit einem Klick prüfen und übernehmen, Live-Statistik, Meldung an Home Assistant per MQTT.
      <br><br>
      <a href="https://github.com/SysriX/haproxy-ui_unraid/actions/workflows/image.yml"><img src="https://github.com/SysriX/haproxy-ui_unraid/actions/workflows/image.yml/badge.svg" alt="Build"></a>
    </td>
    <td width="33%" valign="top">
      <a href="https://github.com/SysriX/certwarden_unraid"><img src="https://raw.githubusercontent.com/SysriX/certwarden_unraid/main/icon.svg" width="56" align="left" alt=""></a>
      <b><a href="https://github.com/SysriX/certwarden_unraid">certwarden-unraid</a></b><br>
      <sub>Zertifikate</sub>
      <br clear="left"><br>
      Let's-Encrypt mit Weboberfläche, automatisch erneuert. DNS-01 für STRATO und Hetzner, Ablage auf eine Unraid-Freigabe für andere Container.
      <br><br>
      <a href="https://github.com/SysriX/certwarden_unraid/actions/workflows/image.yml"><img src="https://github.com/SysriX/certwarden_unraid/actions/workflows/image.yml/badge.svg" alt="Build"></a>
    </td>
  </tr>
  <tr>
    <td width="33%" valign="top">
      <a href="https://github.com/SysriX/mira_unraid"><img src="https://raw.githubusercontent.com/SysriX/mira_unraid/main/icon.svg" width="56" align="left" alt=""></a>
      <b><a href="https://github.com/SysriX/mira_unraid">MIRA</a></b><br>
      <sub>Admin-Zentrale</sub>
      <br clear="left"><br>
      Mail · Identity · Routing · Access: AD/Samba, Authentik, HAProxy, nginx, grommunio und Exchange aus einer Oberfläche – neuer Mitarbeiter in einem Rutsch.
      <br><br>
      <a href="https://github.com/SysriX/mira_unraid/actions/workflows/image.yml"><img src="https://github.com/SysriX/mira_unraid/actions/workflows/image.yml/badge.svg" alt="Build"></a>
    </td>
    <td width="33%"></td>
    <td width="33%"></td>
  </tr>
</table>

<details>
<summary><b>Installieren in Unraid</b></summary>
<br>

Bis die Pakete offiziell in Community Applications stehen, die Vorlagen einmal in der Unraid-Konsole holen:

```bash
Z=/boot/config/plugins/community.applications/private/sysrix; mkdir -p $Z; for p in haproxy_unraid:haproxy-unraid haproxy-ui_unraid:haproxy-ui certwarden_unraid:certwarden-unraid mira_unraid:mira; do curl -fsSL https://raw.githubusercontent.com/SysriX/${p%%:*}/main/templates/${p##*:}.xml -o $Z/${p##*:}.xml && echo "${p##*:} ok"; done
```

Danach unter **Apps › Private** installieren. Voraussetzungen und Einstellungen stehen in der Anleitung des jeweiligen Pakets.
</details>

<br>

<a id="home-assistant"></a>
<img src="assets/abschnitt-homeassistant.svg" alt="Home Assistant" width="100%">

<table>
  <tr>
    <td valign="top">
      <b>Bald hier</b><br>
      <sub>Integrationen · Dashboard-Karten · Themes</sub>
      <br><br>
      Eigene Integrationen und Karten für Home Assistant, jeweils als eigenes Repository und über <b>HACS</b> installierbar – mit einem Klick auf „Open in HACS“.
    </td>
  </tr>
</table>

<details>
<summary><b>Installieren über HACS</b></summary>
<br>

1. In HACS oben rechts **⋮ › Benutzerdefinierte Repositories**
2. Adresse des Repositorys eintragen und den Typ wählen (Integration, Dashboard oder Theme)
3. Herunterladen, Home Assistant neu starten, unter **Einstellungen › Geräte & Dienste** hinzufügen
</details>

<br>

<p align="center">
  <sub>Fragen und Fehler bitte als GitHub-Issue im jeweiligen Repository.</sub>
</p>
