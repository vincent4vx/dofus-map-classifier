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
  - `OPENAI_MODEL` (défaut : `gpt-6-luna`)
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
  "model": "gpt-6-luna",
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

Commande `sprites:analyze` (`make sprites`) → `var/sprite_profiles.json`, et un profil par area dans `var/area_profiles/{id}.json`.

- Parcourt toutes les maps décodables (sans rendu) et relève les sprites utilisés : `background` et `ground` (sprites de sol, `g*.swf`), `layer1` et `layer2` (sprites d'objets, `o*.swf`). Les numéros des deux ensembles sont indépendants : la sortie est séparée en `grounds` et `objects`.
- Pour chaque sprite :
  - `layer` : uniquement le layer le plus courant (en nombre de cellules) ;
  - `slope` : sprite de sol dessiné en pente : utilisé sur au moins une cellule en pente (slope de la cellule différent de 0 et 1, 1 étant le sol plat) sur le layer `ground`, et ayant une frame par valeur de pente (15 frames, d'après les métadonnées de l'image exportée par `sprites:images` : un sprite non exporté n'est pas marqué, `make sprites` relance donc l'analyse après l'export des images). Concerne `g4`, `g6`, `g49` et `g69` ; 17 autres sprites de sol sont posés sur des pentes sans variante de pente. Le PNG exporté est la frame du sol plat. Indiqué au modèle dans le contexte de classification, badge et filtre dans la galerie ;
  - `movements` : fréquence (de 0 à 1, somme de 1) de chaque valeur de `movement` des cellules utilisant le sprite, sur les layers `ground`, `layer1` et `layer2` (0 : non marchable, 1 : objet interactif, 2 à 7 : marchable, plus la valeur est grande, plus le chemin est privilégié). Comptée par cellule, arrondie à 6 décimales. Vide pour un sprite utilisé uniquement en background. Donnée au modèle regroupée en marchable / bloqué / objet interactif, en pourcentages (ex : « 54% walkable, 45% blocked (not walkable) ») ;
  - `ground_share` : pour les sprites dont le layer principal est `ground`, part moyenne (de 0 à 1, 4 décimales) des cellules de sol de ses maps couverte par le sprite, sur les maps où il est utilisé comme sol ; null sinon. Donnée au modèle pour distinguer les tuiles de base des variantes ;
  - `bare_share` : pour les sprites dont le layer principal est `background`, part moyenne (de 0 à 1, 4 décimales) des cellules actives sans sol sur les maps qui l'utilisent comme fond ; null sinon. Un fond visible sur la plupart de la map est le terrain lui-même (la plupart des maps extérieures), un fond caché sous les sols ne fait que boucher les trous. Non donnée au modèle ;
  - `cover_share` : pour les sprites d'objets (`layer1`, `layer2`), part moyenne (de 0 à 1, 4 décimales) des cellules de ses maps couvertes par le sprite, sur les maps où il est utilisé comme objet : une cellule est couverte quand son centre est dans le rectangle du sprite (position de la cellule + offset du sprite après rotation et flip, comme le renderer, en pixels du jeu), pour l'une de ses utilisations ; les utilisations qui se chevauchent comptent une fois leurs cellules. Toutes les cellules de la map comptent (inactives comprises). Une grande plaque d'herbe répétée sur la map en couvre la majeure partie (`o3745` à Incarnam), une fleur une ou deux cellules. null pour un sol ou un fond. Non donnée au modèle ;
  - `path` : position des cellules du sprite par rapport au chemin privilégié de leurs maps (cellules actives de `movement` 5 à 7 : une ligne d'une cellule de large tracée à la main au milieu des routes), sur les seules maps qui en ont un (~15 %) : `cells` (nombre de cellules du sprite sur ces maps) et `distances` (fréquence, 4 décimales, des cellules à 0, 1 et 2 cellules de la ligne, diagonales comprises ; le reste est plus loin). Une tuile de route est surtout à 0, une bordure ou une décoration de bord de route à 1. null si le sprite n'est jamais utilisé sur une map avec chemin. Non donnée au modèle ;
  - `example` : utilisation du sprite montrée en exemple au modèle, `{"map", "cell", "rotation", "flip"}` (null pour un background ; `rotation` et `flip` : orientation du sprite sur la cellule ; sur la map choisie, une utilisation non tournée et non retournée est privilégiée) : sur son layer principal, la map où le sprite est le plus utilisé (le sprite est vu dans son contexte habituel, ex : une muraille avec ses autres sections ; à égalité, la première map), et sur cette map la cellule la plus proche du centre (le sprite n'est pas coupé par le bord) ;
  - `neighbors` : voisins fréquents du sprite sur le même layer (son layer principal), pour détecter les objets composés (murailles, clôtures, bâtiments, sols formés de plusieurs tuiles…) et permettre leur génération procédurale. Pour les layers `ground`, `layer1` et `layer2` (vide pour un sprite utilisé uniquement en background). Liste par orientation du sprite (`rotation` de 0 à 3, sur `ground` et `layer1`, et `flip`), triée par nombre de cellules décroissant : `{"rotation", "flip", "cells", "neighbors": [{"x", "y", "id", "rotation", "flip", "count", "frequency"}]}`. Les voisins sont cherchés jusqu'à 4 cellules sur chaque axe de la grille (81 cellules autour), car les gros sprites (murailles d'Astrub…) couvrent plusieurs cellules et sont répétés toutes les 2 à 4 cellules, jamais sur la cellule adjacente. `x` et `y` sont le décalage en coordonnées de cellule du jeu : `x` vers le sud-est (cellule + largeur), `y` vers le sud-ouest (cellule + largeur - 1) ; la cellule suivante sur la ligne est donc en `1,-1`. `count` est le nombre de cellules du sprite ayant ce voisin à ce décalage, `frequency` le ratio sur `cells` (4 décimales) ; triés par `count` décroissant. Les voisins trouvés moins de 2 fois ou sur moins de 10% des cellules sont ignorés. Les décalages sont absolus : un sprite retourné a les voisins de sa version non retournée en miroir. Dans la galerie : mini-carte isométrique des cellules autour (voisin le plus fréquent, bleu si c'est le même sprite, opacité selon la fréquence) et voisins les plus fréquents cliquables, pour 2 orientations au plus, et filtre des sprites ayant un voisin sur au moins 50% de leurs cellules ;
  - `occurrences` (cellules), `maps` (maps qui l'utilisent), `classified_maps` ;
  - `areas`, `sub_areas` (id, nom, nombre de maps, nombre total d'utilisations) : depuis la lang, pour toutes les maps ;
  - `biomes` : biome de la map, ou à défaut celui de sa sub area (maps non classées ou sans biome) ;
  - `indoor` (`indoor` / `outdoor`) et `location_types` : uniquement depuis les maps classées ;
  - `interactive` : objet interactif dessiné par le sprite (`{id, name, type}`), depuis la lang `interactiveobjects_{lang}_{version}.swf` (`IO.g` : numéro de sprite du set `objects` → id, `IO.d` : nom `n` et type `t`). Les types n'ont pas de libellé dans les langs, ils sont nommés d'après leurs objets : `resource` (1), `workshop` (2), `zaap` (3), `fountain` (4), `door` (5), `storage` (6), `pot` (7), `zaapi` (10), `craftsmen list` (12), `paddock` (13), `switch` (14), `class statue` (15). ~176 sprites concernés, tous en `layer2`.
- Chaque valeur d'attribut (area, sub area, biome, intérieur, type de lieu) donne le nombre de maps (une fois par map) et le nombre total d'utilisations du sprite sur ces maps (`{"maps", "occurrences"}`), ce qui donne la fréquence d'utilisation par map dans ce contexte. Le filtrage du bruit et le prompt n'utilisent que le nombre de maps.
- Les valeurs bien moins fréquentes que la valeur principale sont considérées comme du bruit et ignorées : une valeur est gardée si son nombre de maps est ≥ 10 % de celui de la valeur principale (`--min-ratio`).
- Pas d'agrégation par super area (jugée non pertinente).
- Profils par area (`var/area_profiles/{id}.json`, un fichier par area pour éviter un fichier énorme) : un même sprite peut être utilisé très différemment d'une area à l'autre (`o991` dessine les routes de Pandala, mais n'est qu'un décor dispersé à Sidimote), et le profil global mélange ces usages. Pour chaque area : le nombre de maps (total, classées, avec chemin privilégié, par intérieur, biome et type de lieu, ce dernier avec le nombre de maps ayant un chemin), et pour chaque sprite de l'area les mêmes statistiques d'usage que le profil global calculées sur les seules maps de l'area (layer, occurrences, maps, biomes, movements, `ground_share`, `bare_share`, `path`, `cover_share`), ventilées par type de lieu (`maps`, `occurrences`, `ground_share`, `bare_share`, `path`, `cover_share`). Les distributions sont complètes (pas de filtrage du bruit). Les voisins et le type restent globaux : les voisins décrivent l'assemblage d'un sprite, qui dépend peu de l'area, et quintupleraient la taille des fichiers.
- `areas:export` (fin de `make sprites`) : écrit `var/areas/{id}.json`, le profil de chaque area précédé d'un résumé par type de lieu destiné à la génération (voir [data/areas.md](data/areas.md)) : fonds (`bare_share`), tuiles de sol de base (`ground_share` ≥ 10 %, hors transitions) et pièces de chemin (catégorie `path` du type global, ou cellules sur la ligne de chemin privilégié ou contre elle au moins deux fois plus souvent qu'un sprite moyen), utilisées sur au moins 5 % des maps du type. Beaucoup d'areas n'ont aucun chemin privilégié (Pandala : aucune de ses 249 maps) : les pièces de chemin y sont trouvées par leur catégorie, et leur usage par type de lieu dit lesquelles conviennent (à Pandala, `o991` sur 81 % des routes, `o2250` sur aucune mais sur 28 % des villages). Aucun appel au modèle : le type est la classification globale.
- `sprites:images` : rendu direct des sprites utilisés en PNG, tel qu'envoyé au modèle, dans `var/sprites/{grounds|objects}/{id}.png` (extraction SVG en mémoire via ArakneSwf, puis rendu : marges transparentes retirées, agrandi à ~512 px (x16 max), fond gris neutre `#a0a0a0`). Les métadonnées (taille visible en pixels du jeu, nombre de frames, chemin de l'image) sont stockées dans `var/sprites/{set}/metadata.json`, et jointes à l'export (`image`). ~5 min et ~440 Mo pour ~4 800 sprites.
- `sprites:export --format=html` : galerie HTML `var/sprites.html` (sprite + données agrégées), filtres : recherche (id, nom, catégorie, mots-clés, objet interactif), objet interactif (type), set, layer, biome, area, intérieur, type de lieu, nombre minimum de maps, tri ; pagination par 200. Avec un filtre de biome, d'area, d'intérieur ou de type de lieu, chaque sprite affiche son utilisation dans ce contexte (maps, utilisations, utilisations par map), et les tris (maps, utilisations, utilisations par map) l'utilisent. Les filtres sont indépendants : leurs compteurs ne sont pas croisés.
- `sprites:examples` : map d'exemple de chaque sprite (`example` du profil), avec un cadre magenta de 3 px à 2 px autour du sprite, dans `var/sprite_examples/{set}/{id}-{map}-{cell}.png` (le nom contient l'exemple : un nouvel exemple choisi par l'analyse donne un nouveau fichier). Le rendu de la map (`var/renders`) est réutilisé s'il existe ; la position du sprite est calculée comme le fait le renderer (position de la cellule + offset du sprite après rotation et flip, puis échelle de la map). Une seule utilisation est encadrée, les autres copies du sprite restent visibles autour. Si le sprite est retourné sur la cellule, la map est mise en miroir après le cadre, pour montrer le sprite dans le même sens que sur sa propre image. Le magenta n'est pas utilisé par les maps, le cadre ne peut donc pas être confondu avec la map ; il reste dans l'image pour un sprite au bord de la map. Les sprites vides ou absents du client ne sont pas dessinés par le renderer, et n'ont pas d'exemple (10 sprites). ~3 min et ~2 Go pour ~4 700 exemples. Lien « map » sur chaque sprite de la galerie (`example_image` de l'export).
- `make sprites` enchaîne `sprites:analyze`, `sprites:images`, `sprites:analyze` (pour le flag `slope`), `sprites:examples`, `sprites:export` (JSON et HTML) et `areas:export`.

### Type des objets (étape 3, partie 2)

Commande `sprites:classify` (`make sprites-classify`), via l'API Responses (requêtes parallèles).

- Le PNG exporté par `sprites:images` est envoyé tel quel, suivi de la map d'exemple de `sprites:examples` si elle existe (JPEG agrandi x2, comme les maps classées). Les instructions expliquent le cadre magenta (il ne fait pas partie de la map, le sprite peut y être en partie caché, d'autres copies peuvent être visibles) et demandent de décrire le sprite lui-même, pas la map ; le contexte indique que la seconde image est la map d'exemple. Sur les murailles d'Astrub, `o1905` passe de « cut tree trunk » (tree) à « timber-braced wall section » (wall), et `o1843` de « house corner section » (building) à « wood and stone wall section » (wall). Coût : ~2 700 tokens d'entrée de plus par sprite.
- Contexte donné au modèle : taille visible en pixels du jeu (une cellule = 53 x 27 px), layer principal et son rôle, nombre de maps, biomes, areas, sub areas, intérieur / extérieur, types de lieu. Les sprites connus comme marqueurs de téléportation (1029, 1030) sont signalés.
- Réponse :
  - `category` : liste fermée pour faciliter la recherche (tree, bush, grass, flower, plant, crop, mushroom, rock, cliff, water, ground, path, floor, building, wall, roof, door, window, stairs, bridge, fence, furniture, container, light, statue, tomb, sign, tool, vehicle, decoration, remains, creature, shadow, effect, marker, other) ;
  - `name` : nom libre court (ex : "pine tree") ;
  - `keywords` : mots-clés libres décrivant le sprite (pas de biome ni de lieu, déjà connus) ;
  - `description` ;
  - `usage` : utilisation du sprite pour construire les maps, selon son layer principal (null pour les backgrounds, et pour les types trouvés avant l'ajout du champ). Le schéma de sortie est construit par requête, avec seulement les usages du layer du sprite :
    - `ground` : `fill` (tuile de base répétée pour couvrir une zone : herbe, sable, terre, eau, parquet, dallage ; y compris les tuiles d'un motif sur plusieurs cellules et les tuiles de transition), `variation` (variante d'une tuile de base avec un détail, posée ponctuellement pour casser la répétition). Pas de valeur pour les bordures (déjà `transition`), les chemins (une même tuile de terre peut être un chemin ou un sol selon la map), l'eau (`fill`, le mouvement fait la différence) ni les motifs (du `fill`). Le contexte donne la part moyenne des cellules de sol des maps couverte par la tuile (`ground_share` du profil) : une tuile de base couvre une grande partie de ses maps (médiane ~8 %, 10 % des tuiles au-dessus de 55 %), une variante quelques cellules. Sur un échantillon de 12 grounds, cohérent (ex : `g77` → `variation` de `g54` → `fill`, parquets et herbe → `fill`) ;
    - `layer1` : `ground` (détail peint au sol, partie de la surface : chemin, herbe, terre, tapis, flaque, bordure), `small_object` (petit objet posé au sol : pierres, petites plantes, fleurs, os, feuilles), `shadow` (ombre ou tache de lumière au sol) ;
    - `layer2` : `big_object` (grand élément isolé : arbre, gros rocher, statue, gros meuble, puits, atelier, ressource), `wall` (partie d'une barrière linéaire, répétée en ligne : mur, rempart, clôture, palissade, haie, falaise, rambarde), `building` (partie d'un bâtiment : mur de maison ou de tour, toit, porte, fenêtre, escalier, pilier, arche), `decoration` (petite décoration posée sur ou à côté d'autres éléments : lampe, panneau, pot, livre, vaisselle, petit meuble, toile d'araignée).
    Sur un échantillon de 13 sprites, tous cohérents (ex : murailles d'Astrub → `wall`, tour → `building`, ombre de feuillage → `shadow`, armoire → `big_object`, lanterne → `decoration`). Badge et filtre dans la galerie ;
  - `orientation` : direction vers laquelle pointe le sprite tel que dessiné par défaut (non retourné, non tourné), sur l'écran : `top`, `top_right`, `right`, `bottom_right`, `bottom`, `bottom_left`, `left`, `top_left`, ou null si le sprite n'a pas d'orientation claire (élément symétrique ou rond, tuile unie, arbre, rocher), ou pour les types trouvés avant l'ajout du champ. Pour une transition, la direction de l'autre environnement ou matériau, le sprite étant majoritairement le premier (tuile d'eau avec l'herbe en bas : `bottom`). Pour les autres sprites, la direction d'usage : porte d'une maison, assise d'une chaise, face d'une statue, d'un panneau ou d'une cheminée, face visible d'un mur. Dans le schéma : `["string", "null"]` avec les directions et null. Un sprite retourné (flip) a l'orientation en miroir (`SpriteOrientation::flipped()`). Le modèle se fie à la map d'exemple plutôt qu'au texte : avec un exemple retourné, il donnait l'orientation du sprite retourné (statue `o708` → `bottom_left` au lieu de `right`), même averti par le contexte. L'exemple privilégie donc une utilisation non tournée et non retournée, et si le sprite n'est utilisé que retourné sur la map choisie, la map d'exemple est mise en miroir (le sprite y apparaît comme sur sa propre image ; 287 exemples sur 4 731). Seule la rotation (ground et layer1) reste indiquée dans le contexte. Sur un échantillon de 15 sprites, cohérent (ex : chaise → `bottom_right`, statue de panda dont l'exemple est en miroir → `bottom_left`, maisons → porte, arbre et herbe → null). Flèche et filtre dans la galerie ;
  - `transition` : booléen, vrai si le sprite est une transition d'un environnement ou d'un matériau à un autre, dessinée pour joindre deux zones différentes (eau vers herbe, herbe vers sable, bord de chemin, neige vers herbe, bord de falaise…), faux pour un sprite d'un seul environnement ou matériau, même posé au sol (arbre, mur, meuble). `null` pour les types trouvés avant l'ajout du champ (à reclasser avec `sprites:classify --force`). Sur un échantillon : rivages (`o813`, `o4259`, `o2096`) détectés, murs et sols unis non. Badge et filtre dans la galerie (les types sans la valeur ne correspondent à aucun des deux filtres).
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
