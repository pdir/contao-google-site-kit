# Google Site Kit für Contao

Bringt die Funktionen des WordPress-Plugins [Site Kit by Google](https://wordpress.org/plugins/google-site-kit/) nach Contao 5 und 6:

| Dienst | Was das Bundle macht |
| --- | --- |
| **Search Console** | Klicks, Impressionen, CTR und Ø Position mit Vergleich zum Vorzeitraum, Klicks pro Tag, Top-Suchanfragen, Top-Seiten |
| **Analytics 4** | Tracking-Code (gtag.js) mit Consent Mode v2 im Frontend; im Backend aktive Nutzer, Sitzungen, Aufrufe, Engagement-Rate, Sitzungsdauer, Top-Seiten, Kanäle |
| **PageSpeed Insights** | Performance-Score, Core Web Vitals aus echten Nutzerdaten (CrUX) und Lighthouse-Labordaten, größte Optimierungspotenziale |
| **Tag Manager** | Container-Code im `<head>` und `<noscript>`-Fallback nach `<body>` |

Alles läuft pro **Website-Startpunkt**: Jede Root-Seite bekommt ihre eigene Search-Console-Property, GA4-Property, Mess-ID und ihren eigenen Container. Das Dashboard liegt im Backend unter **System › Site Kit**.

## Voraussetzungen

- Contao **5.3 LTS bis 5.7** oder Contao **6.x** (eine Codebasis)
- PHP 8.1+ (Contao 6 selbst verlangt PHP 8.4)
- PHP-Erweiterung `sodium` (in PHP enthalten)
- Backend erreichbar über **HTTPS** (Google akzeptiert keine HTTP-Weiterleitungs-URIs)

## Installation

Das Bundle ist noch nicht auf Packagist. Bis dahin aus einem Git-Repository oder lokalen Ordner installieren (URL anpassen):

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/<organisation>/contao-site-kit" }
    ],
    "require": {
        "pdir/contao-site-kit": "dev-main"
    }
}
```

Für die lokale Entwicklung stattdessen `{ "type": "path", "url": "../contao-site-kit" }` verwenden.

Danach:

```bash
composer update pdir/contao-site-kit
vendor/bin/contao-console contao:migrate    # legt tl_pdir_sitekit und die tl_page-Felder an
vendor/bin/contao-console cache:clear
```

Über den Contao Manager genügt nach dem Eintragen des Repositories „Pakete aktualisieren“ und anschließend die Datenbank-Aktualisierung.

## Einrichtung (ca. 10 Minuten)

Das WordPress-Plugin meldet sich über einen Proxy-Dienst an, den Google nur für WordPress betreibt. Für Contao braucht es deshalb einen eigenen OAuth-Client. Das Backend führt unter **System › Site Kit › Einstellungen** Schritt für Schritt durch:

1. **Google Cloud Projekt** anlegen: <https://console.cloud.google.com/projectcreate>
2. **APIs aktivieren** (APIs & Dienste › Bibliothek):
   - Google Search Console API
   - Google Analytics Data API
   - Google Analytics Admin API
   - optional Google Site Verification API (für „Bei Google bestätigen“)
   - optional PageSpeed Insights API (nur nötig, wenn Sie einen API-Schlüssel verwenden)
3. **Google Auth Platform / OAuth-Zustimmungsbildschirm** einrichten und den Status auf **„In Produktion“** stellen. Im Status „Testen“ laufen die Freigaben nach 7 Tagen ab. Solange die App nicht von Google verifiziert ist, zeigt Google beim Verbinden einen Warnhinweis an, den Sie als Administrator bestätigen können.
4. **OAuth-Client** vom Typ *Webanwendung* anlegen und als autorisierte Weiterleitungs-URI eintragen, was die Einstellungsseite anzeigt, z. B. `https://www.example.com/contao/site-kit/oauth`.
5. Client-ID und Clientschlüssel in den Einstellungen speichern **oder** in der `.env.local` setzen (siehe unten).
6. **Mit Google verbinden** und alle angefragten Zugriffe erlauben (nur Lesezugriff).
7. Pro Website die Properties auswählen. Die GA4-Mess-ID wird automatisch aus dem passenden Web-Datenstream ermittelt. **Tracking-Code ausgeben** aktivieren.

Die Felder stehen zusätzlich in der Seitenstruktur am Startpunkt (Legende „Google Site Kit“). Der Tracking-Code funktioniert auch ganz ohne Google-Verbindung, wenn Sie die IDs dort von Hand eintragen.

## Konfiguration

Alle Werte sind optional.

```yaml
# config/config.yaml
pdir_site_kit:
    google:
        client_id: '%env(default::SITEKIT_GOOGLE_CLIENT_ID)%'
        client_secret: '%env(default::SITEKIT_GOOGLE_CLIENT_SECRET)%'
        pagespeed_api_key: '%env(default::SITEKIT_PAGESPEED_API_KEY)%'
    cache_ttl: 3600              # Sekunden, wie lange Berichte zwischengespeichert werden
    dashboard:
        admins_only: true        # false: alle Backend-Nutzer sehen die Berichte
    tracking:
        skip_preview: true       # kein Tracking in der Frontend-Vorschau
        skip_backend_users: false
```

```dotenv
# .env.local
SITEKIT_GOOGLE_CLIENT_ID=1234567890-abc.apps.googleusercontent.com
SITEKIT_GOOGLE_CLIENT_SECRET=GOCSPX-…
```

Werte aus der Konfiguration haben Vorrang vor den im Backend gespeicherten. Einstellungen und Google-Verbindung sind immer Administratoren vorbehalten.

## Datenschutz und Consent

- Der Tracking-Code setzt den **Google Consent Mode v2** standardmäßig auf *abgelehnt* (`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`). Ihr Cookie-Banner muss nach der Einwilligung `gtag('consent', 'update', {analytics_storage: 'granted', …})` aufrufen. Pro Website kann der Standard auf *erteilt* gestellt werden.
- Ist für die Seite eine **Content Security Policy** aktiv, bekommen die Skripte eine Nonce und die Google-Hosts werden in `script-src`, `connect-src`, `img-src` und `frame-src` ergänzt.
- Refresh-Token und Clientschlüssel werden mit libsodium verschlüsselt in der Datenbank gespeichert. Der Schlüssel wird aus `APP_SECRET` abgeleitet. Ändert sich `APP_SECRET`, muss Site Kit neu verbunden werden.
- Site Kit fordert nur `webmasters.readonly`, `analytics.readonly`, `openid` und `email` an.

## Aufbau

```
config/                 services.yaml, routes.yaml
contao/dca/             tl_page-Felder, Tabelle tl_pdir_sitekit (Schlüssel/Wert)
contao/languages/       Feldbezeichnungen de/en
public/                 sitekit.css, sitekit.js (→ bundles/pdirsitekit/)
templates/              Twig-Templates für Dashboard, Einstellungen, PageSpeed
translations/           pdir_site_kit.de.yaml / .en.yaml
src/
  Controller/Backend/   Dashboard, Einstellungen, OAuth, PageSpeed (Backend-Routen)
  Dashboard/            Aufbereitung: Zeiträume, Zahlenformat, Sparklines
  EventListener/        Backend-Menü, Tracking-Code im Frontend
  Google/               OAuth-Client, verschlüsselte Ablage, Cache
  Module/               SearchConsole, Analytics, PageSpeed (REST, ohne google/apiclient)
  Site/                 Website-Konfiguration je Startpunkt
  Tracking/             gtag.js- und Tag-Manager-Snippets
```

Technische Entscheidungen:

- **Keine Google-PHP-Bibliothek.** Die APIs werden direkt über den Symfony HttpClient angesprochen. Das hält die Abhängigkeiten klein (google/apiclient bringt über 20 MB mit).
- **Tracking per `kernel.response`-Listener** statt über Templates. Das funktioniert unabhängig von Seitenlayout und Template-Engine identisch in Contao 5 (HTML5- oder Twig-Templates) und Contao 6. Der Code landet auch im HTTP-Cache; beim Speichern auf der Site-Kit-Seite werden die gecachten Seiten der Website über das Cache-Tag `contao.db.tl_page.<Startpunkt-ID>` verworfen.
- **Backend-Controller** erweitern `Contao\CoreBundle\Controller\AbstractBackendController`. Contao 5.7 hat diese Klasse zugunsten von `…\Controller\Backend\AbstractBackendController` als veraltet markiert; sie funktioniert in Contao 6 aber weiter und ist die einzige, die es in 5.3–5.6 gibt. Für Contao 7 muss nur `AbstractSiteKitController` umgestellt werden.
- Search-Console-Daten enden zwei Tage vor heute, weil Google sie verzögert finalisiert. GA4-Daten enden gestern.

## Bekannte Grenzen

- Ein Google-Konto pro Contao-Installation (wie bei Site Kit pro WordPress-Installation).
- AdSense und das Erstellen neuer GA4-Properties direkt aus dem Backend sind nicht enthalten.
- Die Liste der Tag-Manager-Container wird nicht von Google geladen; die Container-ID wird eingetragen.
