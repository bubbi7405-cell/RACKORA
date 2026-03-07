# 🖥️ Rackora

Willkommen bei **Rackora**, dem hochdetaillierten Browser-basierten Infrastruktur- und Datacenter-Management-Spiel. Baue dein eigenes Hosting-Imperium auf, verwalte Server-Hardware, optimiere den Stromverbrauch und stelle dich globalen Krisen!

---

## 🚀 Installation & Setup

Rackora basiert auf **Laravel 11**, **Vue.js 3** (via Inertia.js) und benötigt eine aktuelle PHP- und Node-Umgebung.

### Systemvoraussetzungen
- PHP 8.2 oder höher
- Composer
- Node.js (v18+) & NPM/Yarn/PNPM
- MySQL 8.0+ oder PostgreSQL
- Redis (für Queues & Caching empfohlen)

### Schritt-für-Schritt Anleitung

1. **Repository klonen**
   ```bash
   git clone https://github.com/codepony/rackora.git
   cd rackora
   ```

2. **Abhängigkeiten installieren**
   ```bash
   # PHP / Laravel Dependencies
   composer install

   # Node.js / Frontend Dependencies
   npm install
   ```

3. **Umgebung konfigurieren**
   Kopiere die `.env.example` zu `.env`:
   ```bash
   cp .env.example .env
   ```
   Passe anschließend in der `.env`-Datei die Datenbankzugangsdaten (`DB_*`) an dein System an.

4. **Applikations-Schlüssel generieren**
   ```bash
   php artisan key:generate
   ```

5. **Datenbank migrieren und Seed-Daten laden**
   ```bash
   php artisan migrate --seed
   ```

6. **Frontend kompilieren / Dev-Server starten**
   ```bash
   npm run dev
   ```

7. **Backend / PHP-Server starten**
   In einem neuen Terminal-Fenster:
   ```bash
   php artisan serve
   ```
   Die Applikation ist nun standardmäßig unter `http://localhost:8000` erreichbar.

---

## ❓ FAQ (Häufig gestellte Fragen)

**Frage: Brauche ich einen speziellen Game-Server-Daemon?**  
Nein. Rackora nutzt Laravels Scheduler und Queues (`php artisan queue:work` und `php artisan schedule:work`), um Spiel-Ticks, Einkommen und Events asynchron abzuarbeiten.

**Frage: Wie komme ich ins Admin-Panel?**  
Setze in der Datenbank bei deinem Benutzer den Wert `is_admin = 1` oder nutze initial ein Seed-Skript, welches einen Admin-Account erstellt. Anschließend wird dir das Admin-Dashboard (Simulation Lab etc.) freigeschaltet.

**Frage: Wie funktioniert das Mega QA System?**  
Rackora bietet ein integriertes automatisiertes QA-System zur Prüfung der Spielbalance und zur Erkennung von Exploits. Nutze dazu das Admin-Panel oder den Artisan-Command:
`php artisan qa:mega --bots=10 --ticks=20 --clean`

**Frage: Gibt es einen Multiplayer?**  
Ja! Das Spiel ist als hybrides Singleplayer/Multiplayer-Erlebnis konzipiert. Globale Events und der Black Market beeinflussen alle Spieler auf dem Server gleichzeitig.

---

## 📄 Lizenz

Dieses Projekt ist lizenziert unter der **MIT License**. Siehe die beiliegende [LICENSE](LICENSE) Datei für detaillierte Informationen.

Copyright (c) 2026 codepony.de
