# Map Classifier — Spécifications

Classification automatique des maps (et à terme des objets de maps) de Dofus Retro.

## Phases

1. **Étape 1 (en cours)** : rendu de chaque map et classification par biome (+ infos complémentaires) via l'API OpenAI. Résultats en JSON.
2. **Étape 2** : associer à chaque map son area et sub area.
3. **Étape 3** : analyser chaque objet de chaque map (+ background) pour lui associer area, sub area, biome et type d'objet (house, tree, …).

## Étape 1 — Classification des maps par biome

### Sources

- Client Dofus 1.29 : `data/maps/{id}_{date}X.swf` (~9200 maps), graphismes dans `clips/gfx`.
- Map keys : https://github.com/Daweyy/MapKeys-DR (`maps/{id}_{date}.txt`, clé en hexa), téléchargées via une commande CLI (archive tarball du dépôt, l'API contents étant limitée à 1000 entrées).
- Parsing / rendu : [arakne/php-map-parser](https://github.com/Arakne/php-map-parser).

### Environnement

- Projet PHP via composer : `arakne/php-map-parser`, `symfony/console`, client HTTP, PHPUnit.
- Dockerfile dédié : PHP 8.4 CLI (php-map-parser impose `~8.4`), extensions gd, imagick, zlib, librsvg, JIT activé. Makefile pour les commandes courantes.
- Client Dofus monté en volume.
- Configuration par variables d'environnement :
  - `DOFUS_PATH` : chemin du client Dofus
  - `OPENAI_API_KEY`
  - `OPENAI_MODEL` (défaut : `luna`)
  - `VAR_DIR` (défaut : `var/`)

### Commandes CLI

| Commande | Rôle |
|---|---|
| `keys:download` | Télécharge les clés MapKeys-DR dans `var/keys/` |
| `maps:render [ids…]` | Rend chaque map en taille native (742×432) dans `var/renders/{id}.png`. Échecs (clé manquante, erreur de rendu) enregistrés, le traitement continue |
| `classify:submit` | Construit le JSONL des maps rendues non classées et le soumet à la Batch API OpenAI (detail=high, structured output) |
| `classify:run [ids…]` | Classification synchrone via l'API Responses (requêtes parallèles `--concurrency`, retry 429/5xx). Utile pour tester ou retraiter quelques maps. Ignore les maps d'un batch en cours sauf `--include-pending` |
| `classify:status` | État des batchs en cours |
| `classify:import` | Récupère les résultats, écrit `var/results/{id}.json` |
| `export` | Fusionne les résultats dans un `maps.json` indexé par ID |

Toutes les commandes sont idempotentes / reprenables (une map déjà traitée est ignorée sauf `--force`).

Périmètre : toutes les maps du client. Les maps chiffrées sans clé ou en échec sont marquées en erreur.

### Format de sortie (par map)

Enums en anglais, tags et description en anglais.

```json
{
  "id": 10000,
  "status": "ok",
  "error": null,
  "biome": "forest",
  "biome_other": null,
  "indoor": false,
  "location_type": "wild",
  "tags": ["river", "bridge"],
  "description": "…",
  "model": "luna",
  "classified_at": "2026-11-12T12:00:00+00:00"
}
```

- `status` : `ok` | `error`
- `error` : `null` ou code d'erreur (`missing_key`, `render_failed`, `classification_failed`, …)
- `biome` (naturel uniquement) : `forest`, `jungle`, `plain`, `field`, `desert`, `mountain`, `swamp`, `snow`, `beach`, `sea`, `lake_river`, `cave`, `underground`, `volcanic`, `wasteland`, `cemetery`, `other`
- `biome_other` : libellé libre si `biome = other`
- `indoor` : booléen
- `location_type` : `city`, `village`, `road`, `wild`, `dungeon`, `house`, `shop`, `temple`, `mine`, `arena`, `other`

### Architecture

- Services découplés : provider de clés, renderer, client de classification (interface, mockable), stockage des résultats.
- Le résultat par map doit pouvoir être enrichi (area / sub area en étape 2). Les rendus PNG sont conservés pour l'étape 3.
- Tests PHPUnit sans appel réseau.

### Constats techniques

- Toutes les maps du client sont chiffrées (`X.swf`). La clé dépend de la version de la map : une clé d'une autre version ne fonctionne pas.
- 735 maps du client (~8 %) n'ont pas de clé correspondante dans MapKeys-DR : elles sont marquées `missing_key`.
- Le rendu prend ~0,2 s par map (cache de sprites SQLite dans `var/cache`).
- Les images sont envoyées en JPEG (qualité 90, ~100 Ko par requête) : le traitement complet représente ~920 Mo de fichiers batch, découpés en fichiers de 150 Mo max.
- Les maps sans ligne de résultat dans un batch (batch expiré…) restent non classées et seront resoumises.
- Commande supplémentaire `report` : galerie HTML (rendu + classification) pour la validation visuelle.

### Validation

- Tester le prompt sur un échantillon (~20 maps) avec vérification visuelle avant de lancer le traitement complet.
