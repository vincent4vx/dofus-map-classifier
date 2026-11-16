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
- Client Dofus monté en volume (le sien, ou celui téléchargé par `client:download`).
- Configuration par variables d'environnement :
  - `DOFUS_PATH` : chemin du client Dofus
  - `OPENAI_API_KEY`
  - `OPENAI_MODEL` (défaut : `luna`)
  - `VAR_DIR` (défaut : `var/`)

### Commandes CLI

| Commande | Rôle |
|---|---|
| `client:download` | Télécharge un client Dofus Retro depuis [dofedex](https://github.com/dofera/dofedex) (1.41.7 classic, maps de même version que les clés MapKeys-DR) et n'extrait que `data/maps` et `clips/gfx` (`--full` pour tout) dans `var/dofus`. Utilisé par la CI pour les tests |
| `keys:download` | Télécharge les clés MapKeys-DR dans `var/keys/` |
| `maps:render [ids…]` | Rend chaque map en taille native (742×432) dans `var/renders/{id}.png`. Échecs (clé manquante, erreur de rendu) enregistrés, le traitement continue |
| `classify:run [ids…]` | Classifie les maps rendues via l'API Responses (detail=high, structured output, requêtes parallèles `--concurrency`, retry 429/5xx), écrit `var/results/{id}.json` |
| `export [ids…]` | Exporte les résultats avec leur localisation, dans tous les formats par défaut (`--format`) : `json` (`maps.json` indexé par ID + `sub_areas.json`) et `html` (galerie `report.html`). Filtres : sélection de maps, `--biome`, `--only-ok` |

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

- Découpage par domaine (`Location`, `Map`, `Sprite`), plus `Infrastructure` (OpenAI, JSON, langs) et `Application` (configuration, câblage). `Map` et `Sprite` séparent les données lues dans le jeu de leur sous-domaine `Classification` (résultats du modèle). Dans chaque domaine, les classes sont séparées par type : `Command` (commandes console), `Model` (structures de données), `Repository` (accès aux données), `Service` (traitements), `Export` (formats d'export : JSON, HTML, via l'interface `Infrastructure\Export\ExportFormat`), `Exception`. Voir le README.
- Dépendances à sens unique : `Location` ← `Map` ← `Map\Classification` ← `Sprite` ← `Sprite\Classification`. Seul `Application` connaît la configuration et le `Kernel` ; les commandes ne gèrent que l'entrée / sortie console.
- Services découplés : provider de clés, renderer, client de classification (interface, mockable), stockage des résultats.
- Le résultat par map doit pouvoir être enrichi (area / sub area en étape 2). Les rendus PNG sont conservés pour l'étape 3.
- Tests PHPUnit sans appel réseau.

### Localisation et biome des sub areas

- Source : lang `maps_{lang}_{version}.swf` (version la plus récente du dossier `DOFUS_LANG_DIR`), parsé avec ArakneSwf. Variables utilisées : `MA.m` (sub area de chaque map), `MA.sa` (nom, area), `MA.a` (nom, super area), `MA.sua`.
- Le champ `tt` (type de terrain) des sub areas n'est pas utilisé : il n'existe pas dans les langs 1.29. Les coordonnées ne sont pas exportées.
- La localisation est jointe au moment de l'export : `location: {sub_area: {id, name, biome}, area: {id, name}, super_area: {id, name}}`.
- Biome d'une sub area : biome le plus fréquent parmi ses maps classées en extérieur (à défaut, toutes ses maps), les maps sans biome sont ignorées. Exporté dans `var/sub_areas.json` (votes, nombre de maps classées / total) et dans `location.sub_area.biome` de chaque map. Il permet de compléter le biome des maps qui n'en ont pas (intérieurs). Il se fiabilise au fur et à mesure que les maps de la sub area sont classées.

### Analyse des objets (étape 3, partie 1)

Commande `sprites:analyze` (`make sprites`) → `var/sprite_profiles.json`.

- Parcourt toutes les maps décodables (sans rendu) et relève les sprites utilisés : `background` et `ground` (sprites de sol, `g*.swf`), `layer1` et `layer2` (sprites d'objets, `o*.swf`). Les numéros des deux ensembles sont indépendants : la sortie est séparée en `grounds` et `objects`.
- Pour chaque sprite :
  - `layer` : uniquement le layer le plus courant (en nombre de cellules) ;
  - `slope` : sprite de sol dessiné en pente : utilisé sur au moins une cellule en pente (slope de la cellule différent de 0 et 1, 1 étant le sol plat) sur le layer `ground`, et ayant une frame par valeur de pente (15 frames, d'après les métadonnées de l'image exportée par `sprites:images` : un sprite non exporté n'est pas marqué, `make sprites` relance donc l'analyse après l'export des images). Concerne `g4`, `g6`, `g49` et `g69` ; 17 autres sprites de sol sont posés sur des pentes sans variante de pente. Le PNG exporté est la frame du sol plat. Indiqué au modèle dans le contexte de classification, badge et filtre dans la galerie ;
  - `occurrences` (cellules), `maps` (maps qui l'utilisent), `classified_maps` ;
  - `areas`, `sub_areas` (id, nom, nombre de maps) : depuis la lang, pour toutes les maps ;
  - `biomes` : biome de la map, ou à défaut celui de sa sub area (maps non classées ou sans biome) ;
  - `indoor` (`indoor` / `outdoor`) et `location_types` : uniquement depuis les maps classées ;
  - `interactive` : objet interactif dessiné par le sprite (`{id, name, type}`), depuis la lang `interactiveobjects_{lang}_{version}.swf` (`IO.g` : numéro de sprite du set `objects` → id, `IO.d` : nom `n` et type `t`). Les types n'ont pas de libellé dans les langs, ils sont nommés d'après leurs objets : `resource` (1), `workshop` (2), `zaap` (3), `fountain` (4), `door` (5), `storage` (6), `pot` (7), `zaapi` (10), `craftsmen list` (12), `paddock` (13), `switch` (14), `class statue` (15). ~176 sprites concernés, tous en `layer2`.
- Les attributs sont comptés une fois par map (pas par cellule).
- Les valeurs bien moins fréquentes que la valeur principale sont considérées comme du bruit et ignorées : une valeur est gardée si son nombre de maps est ≥ 10 % de celui de la valeur principale (`--min-ratio`).
- Pas d'agrégation par super area (jugée non pertinente).
- `sprites:images` : rendu direct des sprites utilisés en PNG, tel qu'envoyé au modèle, dans `var/sprites/{grounds|objects}/{id}.png` (extraction SVG en mémoire via ArakneSwf, puis rendu : marges transparentes retirées, agrandi à ~512 px (x16 max), fond gris neutre `#a0a0a0`). Les métadonnées (taille visible en pixels du jeu, nombre de frames, chemin de l'image) sont stockées dans `var/sprites/{set}/metadata.json`, et jointes à l'export (`image`). ~5 min et ~440 Mo pour ~4 800 sprites.
- `sprites:export --format=html` : galerie HTML `var/sprites.html` (sprite + données agrégées), filtres : recherche (id, nom, catégorie, mots-clés, objet interactif), objet interactif (type), set, layer, biome, area, intérieur, type de lieu, nombre minimum de maps, tri ; pagination par 200.
- `make sprites` enchaîne `sprites:analyze`, `sprites:images` et `sprites:export` (JSON et HTML).

### Type des objets (étape 3, partie 2)

Commande `sprites:classify` (`make sprites-classify`), via l'API Responses (requêtes parallèles).

- Le PNG exporté par `sprites:images` est envoyé tel quel.
- Contexte donné au modèle : taille visible en pixels du jeu (une cellule = 53 x 27 px), layer principal et son rôle, nombre de maps, biomes, areas, sub areas, intérieur / extérieur, types de lieu. Les sprites connus comme marqueurs de téléportation (1029, 1030) sont signalés.
- Réponse :
  - `category` : liste fermée pour faciliter la recherche (tree, bush, grass, flower, plant, crop, mushroom, rock, cliff, water, ground, path, floor, building, wall, roof, door, window, stairs, bridge, fence, furniture, container, light, statue, tomb, sign, tool, vehicle, decoration, remains, creature, shadow, effect, marker, other) ;
  - `name` : nom libre court (ex : "pine tree") ;
  - `keywords` : mots-clés libres décrivant le sprite (pas de biome ni de lieu, déjà connus) ;
  - `description`.
- Objets interactifs : le nom et le type de l'objet sont donnés au modèle, qui doit reprendre ce nom et choisir la catégorie d'après ce que montre le sprite. Le nom de l'objet interactif est prioritaire : il remplace le nom donné par le modèle lors de la jointure (le nom du modèle et le type d'objet interactif sont ajoutés aux mots-clés). Le résultat brut du modèle reste stocké tel quel.
- Stockage : `var/sprite_types/{grounds|objects}/{id}.json`. Export : `sprites:export` écrit `var/sprites.json`, le profil de chaque sprite (`sprite_profiles.json`) avec son type (`type`, null si non classé), comme `export` pour les maps. L'export (tous les formats) est refait à la fin de `sprites:classify`. Le type est affiché dans `sprites.html` (filtre par catégorie, recherche sur nom et mots-clés, mots-clés cliquables).
- Sélection : ids préfixés (`o345`, `g10-20`), `--set`, `--layer`, `--interactive`, `--min-maps`, `--limit` (les plus utilisés d'abord), `--force`, `--retry-errors`.

### Constats techniques

- Toutes les maps du client sont chiffrées (`X.swf`). La clé dépend de la version de la map : une clé d'une autre version ne fonctionne pas.
- 735 maps du client (~8 %) n'ont pas de clé correspondante dans MapKeys-DR : elles sont marquées `missing_key`.
- Le rendu prend ~0,2 s par map (cache de sprites SQLite dans `var/cache`).
- Les images sont envoyées en JPEG (qualité 90), agrandies x2 (1484x864, option `--image-scale`) : en taille native, le modèle manque de détails et confond certaines textures (ex : plaques d'herbe claire d'Incarnam vues comme de l'eau → `swamp`). Coût : ~2 700 tokens d'entrée par map au lieu de ~1 500. ~250 Ko par requête.
- Format d'export `html` (`export --format=html`) : galerie HTML (rendu + classification) pour la validation visuelle.

### Prompt

- Le modèle remplit d'abord un champ `ground` (description du sol praticable), non stocké, qui sert d'étape de raisonnement avant le choix du biome.
- Règles :
  - juger le biome sur la map elle-même : ignorer le vide, les nuages et le paysage lointain ;
  - `mountain` exige de la vraie roche (parois rocheuses, rochers, sol de pierre) : les falaises rocheuses qui traversent la zone jouable comptent, pas les simples talus de terre ou d'herbe entre deux niveaux ;
  - l'eau prime sur le relief : niveaux bas ou grande partie de la map en eau → biome de l'eau ;
  - `swamp` exige de l'eau stagnante (même trouble ou opaque) couvrant une part importante du sol, les variations de couleur de l'herbe ne sont pas de l'eau ;
  - `forest` = beaucoup d'arbres même petits, `plain` = herbe avec quelques arbres isolés ;
  - `wasteland` = herbe jaune sèche avec arbres morts ;
  - `cemetery` dès que des tombes sont visibles.
- Effort de raisonnement : aucun gain constaté (medium / high) sur les cas ambigus, non activé.
- Stabilité mesurée sur deux passes : Incarnam 80/83, échantillon de 50 maps hors Incarnam 44/50 (instables : cas limites et intérieurs `null` / biome).
- Détection de `swamp` vérifiée sur 25 maps des sub areas de marais : 39/50 `swamp`, le reste surtout `jungle` (marais tropicaux d'Otomaï / Koalak, ambigu) ou des maps sans eau visible.
- Les maps d'Incarnam avec falaises rocheuses peuvent sortir en `mountain` (accepté).

### Validation

- Tester le prompt sur un échantillon (~20 maps) avec vérification visuelle avant de lancer le traitement complet.
