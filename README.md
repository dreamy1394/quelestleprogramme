# Programmes 2027

Plateforme d'information sur les programmes des candidats à l'élection présidentielle française
de 2027. PHP 8 sans framework, sans base de données, sans dépendance externe.

---

## 1. Principe

Tout le contenu vit dans `/data`. Le PHP ne fait que lire ces fichiers et les mettre en page.

**Ajouter un candidat** = déposer un fichier JSON dans `data/candidats/`.
**Ajouter une mesure** = ajouter un objet dans le tableau `mesures` du candidat concerné.
**Ajouter un thème** = ajouter une entrée dans `data/themes.json`.

Aucune de ces opérations ne demande de toucher au code. C'est le choix structurant : d'ici avril
2027 les programmes vont sortir par vagues, et le site doit se mettre à jour en éditant un fichier
texte depuis n'importe quel client FTP.

## 2. Arborescence

```
index.php              Accueil : présentation + grille des candidats
candidat.php           Fiche d'un candidat (?id=slug)
comparateur.php        Lecture croisée thème par thème
methodologie.php       Règles éditoriales et garanties de neutralité
mentions-legales.php   Obligations LCEN
.htaccess              Réécriture d'URL, en-têtes de sécurité, cache

inc/config.php         ⚙️  Seul fichier de configuration
inc/data.php           Chargement et mise en forme des données
inc/header.php         En-tête + navigation
inc/footer.php         Pied de page

data/themes.json       Référentiel des thèmes (l'ordre du fichier = l'ordre d'affichage)
data/candidats/*.json  Un fichier par candidat
data/candidats/_modele.json.txt   Modèle commenté pour ajouter un candidat

assets/css/style.css   Feuille de style unique
assets/img/candidats/  Portraits + LISEZ-MOI sur les licences
```

## 3. Déploiement chez IONOS

1. Dans l'espace client IONOS, vérifier que la version PHP du pack d'hébergement est **8.1 ou
   supérieure** (Hébergement → Configuration PHP). Le site utilise `match`, les propriétés
   nommées et les fonctions fléchées.
2. Transférer l'intégralité du dossier dans le répertoire web (`/` ou le dossier associé au
   domaine), en incluant le fichier `.htaccess` — la plupart des clients FTP masquent les
   fichiers commençant par un point, penser à activer leur affichage.
3. Ouvrir `inc/config.php` et renseigner :
   - `SITE_URL` : le nom de domaine définitif
   - `BASE_PATH` : `''` si le site est à la racine du domaine
   - le bloc mentions légales (éditeur, directeur de publication, contact)
   - `MODE_BROUILLON` → `false`
4. Activer le certificat SSL fourni par IONOS, puis décommenter la redirection HTTPS dans
   `.htaccess`.
5. Vérifier que `https://ledomaine/data/themes.json` renvoie bien une erreur 403 : les données
   ne doivent pas être servies en direct.

**URLs propres** — le `.htaccess` accepte déjà `/candidat/marine-le-pen`. Les liens internes
utilisent la forme `candidat.php?id=…`, qui fonctionne partout. Pour basculer sur les URLs
propres une fois `mod_rewrite` confirmé actif, remplacer dans `index.php`, `candidat.php` et
`comparateur.php` les appels `url('candidat.php?id=' . $slug)` par `url('candidat/' . $slug)`.

## 4. Mode brouillon

`MODE_BROUILLON = true` dans `inc/config.php` affiche :

- un bandeau permanent comptant les mesures encore dépourvues de source,
- un avertissement sous chaque mesure non sourcée,
- un avertissement sur les portraits manquants,
- un rappel sur les mentions légales incomplètes.

C'est un garde-fou de recette. **Le site ne doit pas être mis en ligne publiquement avec ce mode
actif**, mais il ne doit pas non plus être désactivé tant que les avertissements ne sont pas tous
levés.

## 5. Ce qui reste à faire avant une mise en ligne publique

| Chantier | Pourquoi c'est bloquant |
|---|---|
| Sourcer chaque mesure | 43 mesures sur 43 sont actuellement sans source vérifiable. C'est la seule chose qui distingue ce site d'un blog d'opinion. |
| Compléter les mentions légales | Obligation légale (LCEN) : identité de l'éditeur et directeur de la publication. |
| Ajouter les portraits sous licence libre | Voir `assets/img/candidats/LISEZ-MOI.txt`. |
| Relire chaque formulation | Une mesure retranscrite avec les mots du candidat ou avec ceux de ses adversaires ne dit pas la même chose. |
| Compléter les biographies | Les biographies actuelles sont factuelles mais courtes ; les recouper avec une source publique. |

## 6. Chaîne de veille automatisée

### Le principe : git est la source de vérité

```
   Sources officielles
          │
          ▼
   [ tools/veille.php ]        GitHub Actions, tous les jours à 8h
   détecte les mouvements       → aucune interprétation, aucune IA
          │
          ▼
   Issue GitHub « mouvements détectés »
          │
          ▼
   [ tools/admin/ ]            en local, sur ta machine
   tu valides ou tu rejettes    → écrit dans data/candidats/
          │
          ▼
   git commit + push
          │
          ▼
   [ deploy.yml ]              FTPS vers IONOS
```

Ce sens de circulation est délibéré. Le back-office **n'est jamais déployé** : s'il écrivait en
production, le déploiement suivant écraserait tes validations — et une page d'administration
exposée sur un site politique est une cible inutile. On valide en local, git conserve l'historique
complet de chaque mesure, la CI déploie.

### Ce que fait le radar (`tools/veille.php`)

Il lit `data/sources.json`, télécharge chaque page officielle, en extrait le texte utile (menus,
pieds de page et scripts retirés — sans ça, un compteur de visites déclencherait une fausse alerte),
et compare à l'empreinte du passage précédent. La liste des PDF liés entre dans l'empreinte : **un
programme mis en ligne sans que le texte de la page bouge est le cas le plus intéressant**, et
c'est celui qu'une simple comparaison de texte laisserait passer.

Il ne comprend rien à ce qu'il lit, n'appelle aucun modèle de langage, et n'écrit jamais dans
`data/candidats/`. Il répond à une seule question : y a-t-il eu du mouvement ?

```bash
php tools/veille.php --init    # premier passage : référence les sources sans rien signaler
php tools/veille.php           # passages suivants
php tools/veille.php --haute   # uniquement les sources prioritaires
```

Codes de sortie : `0` rien de neuf · `10` changements détectés · `1` erreur.

### Le back-office (`tools/admin/`)

```bash
php -S localhost:8000 -t .
# puis http://localhost:8000/tools/admin/
```

Refuse de s'exécuter depuis une IP autre que `127.0.0.1`. Quatre écrans :

- **À sourcer** — toutes les mesures publiées sans source vérifiable, avec un formulaire par mesure.
  C'est l'écran à ouvrir en premier : 43 mesures y attendent.
- **File d'attente** — les propositions déposées dans `data/_inbox/` par la chaîne d'extraction.
  Une proposition sans citation verbatim ou sans URL est refusée automatiquement.
- **Ajouter une mesure** — saisie directe, avec les mêmes trois champs obligatoires. Pas de régime
  de faveur pour l'humain.
- **Dernier rapport de veille**.

### Mise en service

1. Créer le dépôt GitHub et pousser le projet.
2. Dans *Settings → Secrets and variables → Actions*, créer quatre secrets :
   `IONOS_SFTP_SERVEUR`, `IONOS_SFTP_UTILISATEUR`, `IONOS_SFTP_MOTDEPASSE`, `IONOS_DOSSIER_DISTANT`.
   L'hébergement Linux IONOS utilise **SFTP sur le port 22** (et non FTPS) : le déploiement se fait
   avec `lftp`, sans action tierce, pour qu'aucun code non contrôlé ne manipule les identifiants.
   Ajouter aussi la variable `URL_SITE` (onglet *Variables*) pour le contrôle post-déploiement,
   et `SIMULATION_DEPLOIEMENT = true` le temps du premier essai à blanc.
3. Lancer une fois le workflow *Veille des programmes* à la main avec l'option `init` cochée :
   ça référence les sources sans générer de fausse alerte au premier passage.
4. Créer le label `veille` dans les issues du dépôt (sinon le workflow se rabat sur le résumé du job).
5. Vérifier le rapport du premier passage : les sources marquées `"verifie": false` dans
   `data/sources.json` n'ont pas été confirmées, celles qui échouent sont à corriger à la main.

Le workflow de déploiement **refuse de publier** si `MODE_BROUILLON` est encore à `true` ou si les
mentions légales contiennent des champs à compléter. C'est volontairement bloquant.

### Ce qui n'est pas automatisé, et pourquoi

L'extraction des mesures par un modèle de langage est faisable et prévue (`data/_inbox/` et la file
d'attente existent déjà pour ça), mais elle n'a de sens que sous trois contraintes :

1. **Sources officielles uniquement.** Un modèle qui lit un article de presse restitue la
   formulation du journaliste, pas celle du candidat. La presse est un signal, pas une matière.
2. **Citation verbatim obligatoire.** Sans la phrase exacte du document, impossible de vérifier
   que la mesure n'a pas été déformée ou inventée. Le back-office rejette sans citation.
3. **Aucune publication automatique.** La sortie va dans la file d'attente, jamais dans les
   fichiers en production.

Le gain réel de cette chaîne n'est pas « le site se remplit tout seul » — c'est **10 minutes de
validation par jour au lieu de 2 heures de veille**.

## 7. Évolutions envisageables

- **Historique des versions** : conserver les anciennes valeurs d'une mesure pour montrer les
  évolutions de programme au fil de la campagne. Un dossier `data/archives/AAAA-MM-JJ/` suffirait.
- **Flux RSS** des mises à jour de programme.
- **Export JSON public** (`/api/candidats.json`) : les données étant déjà en JSON, l'ouverture
  aux journalistes et aux chercheurs coûte une dizaine de lignes.
- **Recherche plein texte** côté client sur l'ensemble des mesures.
- **Mode « je ne regarde que les mesures »** : masquer les noms et les partis pour lire les
  propositions à l'aveugle. C'est le genre de fonctionnalité qui fait le succès d'un site comme
  celui-ci et qui est cohérente avec le parti pris de neutralité.
