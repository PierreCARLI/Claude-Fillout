# Occitanie Angels — Routeur Fillout / Airtable

## Le problème

Fillout ne propose que deux modes pour un formulaire lié à Airtable :
- **Création** : crée toujours un nouvel enregistrement (rejette les emails déjà présents dans la table Contacts).
- **Modification** : nécessite un `record ID` Airtable dans l'URL, différent pour chaque contact.

Impossible de poster un lien unique sur LinkedIn ou le site qui fonctionne dans les deux cas.

## La solution

Une page d'accueil demande l'email du visiteur, vérifie **côté serveur** (jamais côté navigateur, pour ne pas exposer la clé API Airtable) si cet email existe déjà dans la table Contacts, puis redirige :

- Email trouvé → formulaire Fillout en mode **Modification**, avec `?id=<recordId>` ajouté automatiquement.
- Email non trouvé → formulaire Fillout en mode **Création**.

C'est l'approche à privilégier : elle est fiable, ne nécessite pas de dupliquer les formulaires par contact, et reste simple pour les visiteurs (un seul lien à partager).

## Implémentation fournie

Un plugin WordPress autonome, adapté à un hébergement WordPress/OVH classique (pas besoin de serveur Node, de fonctions serverless ni d'accès SSH) :

```
wordpress-plugin/occitanie-angels-fillout-router/
├── occitanie-angels-fillout-router.php   # Plugin principal (réglages + endpoint REST + shortcode)
└── assets/
    ├── router.css
    └── router.js
```

- Le token Airtable et l'appel à l'API Airtable restent **côté serveur** (endpoint REST WordPress), jamais exposés dans le HTML/JS envoyé au navigateur.
- Une limite de requêtes par minute/IP et un champ honeypot limitent les abus (scan automatisé d'emails).
- **Plusieurs configurations** : le plugin n'est pas limité à un seul couple de formulaires. Vous pouvez créer autant de configurations nommées que nécessaire (une par table Airtable / couple de formulaires Fillout), et les utiliser sur des pages différentes.

## Installation

1. Compressez le dossier `wordpress-plugin/occitanie-angels-fillout-router/` en `.zip`, ou déposez-le via FTP/SFTP OVH dans `wp-content/plugins/`.
2. Dans l'admin WordPress : **Extensions** → activez **Occitanie Angels - Fillout Router**.
3. Allez dans **Réglages → Fillout Router**. Une première configuration, nommée `default`, est déjà pré-remplie :
   - Base Airtable : `appQwliKqpfcSOYb0`
   - Table Contacts : `tblmVqV8aXjNauqBl`
   - Champ email : `Email` *(à corriger si le nom exact du champ dans votre table diffère)*
   - Formulaire Création : `https://occitanieangels.fillout.com/t/tajiPdzR5uus`
   - Formulaire Modification : `https://occitanieangels.fillout.com/t/nAxnHFpKNmus`
   - Paramètre d'URL du Record ID : `id`
4. Renseignez votre **Personal Access Token Airtable** (créé sur [airtable.com/create/tokens](https://airtable.com/create/tokens), scope `data.records:read`, avec accès à **toutes les bases** utilisées par vos configurations — un seul token sert à toutes). Deux options :
   - Le saisir directement dans le champ de réglages (stocké en base WordPress), ou
   - (recommandé, plus sûr) l'ajouter dans `wp-config.php` :
     ```php
     define('OA_FILLOUT_AIRTABLE_TOKEN', 'patXXXXXXXXXXXXXX');
     ```
5. Créez (ou éditez) la page WordPress qui servira de page d'accueil, et insérez-y le shortcode :
   ```
   [fillout_router]
   ```
   (équivalent à `[fillout_router config="default"]`)
6. Publiez cette page, et c'est **son URL** (pas celles de Fillout) que vous partagez sur LinkedIn et le site.

## Ajouter une nouvelle configuration (un autre couple de formulaires)

Dans **Réglages → Fillout Router**, une ligne vierge « Ajouter une nouvelle configuration » se trouve en bas de la liste. Renseignez :
- un **identifiant (slug)** court, ex. `evenement-2026` (lettres minuscules, chiffres, tirets)
- le libellé, la base/table/champ email Airtable, et les deux URLs Fillout (+ paramètre du Record ID) propres à cet usage

Enregistrez, puis utilisez cette configuration sur la page de votre choix avec :
```
[fillout_router config="evenement-2026"]
```
Vous pouvez avoir autant de configurations que nécessaire, actives en même temps sur des pages différentes (ou même plusieurs shortcodes sur une même page). Chaque configuration peut être modifiée ou supprimée indépendamment (case « Supprimer cette configuration »).

## Mode dynamique (un formulaire Fillout par événement, sans multiplier les pages WordPress)

Cas d'usage : un formulaire d'inscription différent par événement, qui crée des enregistrements dans une table "Fiche de présence" liée à Contacts via un champ de sélection (Record Picker). Le risque : les visiteurs cliquent "Create new" sur ce champ alors que leur fiche Contact existe déjà, créant des doublons.

La solution reprend le même principe de vérification d'email, mais avec **une seule configuration et une seule page WordPress qui servent à tous les événements** — l'URL du formulaire Fillout de l'événement est fournie dans le lien partagé, pas dans les réglages.

**Mise en place (une fois) :**
1. Dans **Réglages → Fillout Router**, créez une configuration (ex. slug `evenement`), avec la base/table/champ email de **Contacts** (c'est toujours Contacts qu'on interroge pour détecter les doublons).
2. Cochez **Formulaire dynamique**.
3. Renseignez le **Paramètre d'URL pour le Record ID** : utilisez toujours le même nom (ex. `contact_id`) dans tous vos formulaires Fillout d'événements.
4. Créez une page WordPress (ex. `/inscription-evenement/`) avec `[fillout_router config="evenement"]`, et renseignez son URL dans le champ **URL de la page WordPress** de la configuration (sert au générateur de lien).

**Pour chaque nouvel événement :**
1. Créez votre formulaire Fillout comme d'habitude. Sur le champ Record Picker ("NOM Prénom" ou équivalent), configurez le préremplissage par URL avec le paramètre choisi ci-dessus (ex. `contact_id`) — ⚠️ à localiser dans les réglages du champ ou du formulaire Fillout, sa valeur attendue est le Record ID Airtable (`recXXXXXXXXXXXXXX`).
2. Dans **Réglages → Fillout Router**, sur la configuration `evenement`, utilisez le **Générateur de lien** : collez l'URL de ce nouveau formulaire Fillout, cliquez sur "Générer le lien", copiez le résultat.
3. Partagez ce lien (pas l'URL Fillout brute) sur LinkedIn / votre site.

Aucune page ni configuration supplémentaire à créer dans WordPress, quel que soit le nombre d'événements. Par sécurité, le plugin n'accepte que des URLs `fillout.com` dans le lien partagé — toute autre valeur est rejetée, pour empêcher un détournement du domaine du site vers un site tiers.

**Limite à connaître :** le préremplissage réduit fortement le risque de doublon (le champ arrive déjà rempli avec le bon contact), mais n'empêche pas techniquement quelqu'un de cliquer "Create new" quand même. Pour une garantie totale, voir la section suivante.

## Élimination totale des doublons : passer par le formulaire de contact avant l'événement

Plutôt que de compter sur le préremplissage (qui réduit le risque sans l'éliminer), vous pouvez configurer le plugin pour qu'un email non trouvé soit **d'abord** envoyé vers un formulaire de création de contact, puis renvoyé automatiquement vers la page d'accueil une fois le contact créé — qui le retrouvera cette fois et le redirigera normalement vers le formulaire événement. Résultat : **tout le monde arrive au formulaire événement avec une fiche Contact déjà existante**, donc vous pouvez désactiver définitivement "Can create new records" sur son champ Record Picker, dans tous vos formulaires événements, sans exception à gérer.

**Important : utilisez un formulaire dédié, pas votre formulaire d'adhésion directement.** Ce mécanisme configure une redirection **inconditionnelle** en fin de formulaire — si vous la mettiez sur votre formulaire d'adhésion existant, elle s'appliquerait aussi aux personnes qui le remplissent normalement pour adhérer (hors contexte événement), ce qui n'est pas souhaité. La solution : **dupliquez votre formulaire d'adhésion une seule fois** (pas par événement) pour créer un formulaire "Création de contact — événements" dédié, qui ne sera jamais rempli en dehors de ce parcours, et n'appliquez la redirection qu'à cette copie. Le formulaire d'adhésion original reste inchangé.

**Mise en place (une fois) :**
1. Dans Fillout, dupliquez votre formulaire d'adhésion (mode Création) → renommez la copie, ex. "Création de contact — événements".
2. Sur la configuration `evenement`, renseignez **Formulaire de création de contact** avec l'URL de **cette copie** (pas celle de l'adhésion originale).
3. Laissez **Paramètre de retour** à sa valeur par défaut (`next`), ou choisissez-en une autre.
4. ⚠️ Dans Fillout, sur cette copie uniquement, réglages "Après soumission" / "Confirmation" : configurez une redirection vers l'URL reçue dans le paramètre `next` de l'URL d'arrivée (généralement via une balise de fusion référençant les paramètres d'URL entrants — le nom exact de cette fonctionnalité est à vérifier dans votre éditeur Fillout, elle n'a pas pu être confirmée depuis cet environnement). Comme cette copie ne sert qu'à ce parcours, la redirection peut être inconditionnelle sans risque.

Une fois ces étapes faites, tous vos formulaires événements peuvent avoir "Can create new records" désactivé sur le champ Record Picker — plus aucun doublon possible par ce biais, quel que soit le nombre d'événements, sans configuration Fillout supplémentaire par événement. Le formulaire d'adhésion original continue de fonctionner normalement, sans aucune redirection surprise.

**Sécurité :** le paramètre de retour n'est accepté que s'il pointe vers une page de votre propre site (le plugin vérifie le nom de domaine), pour empêcher qu'il serve à détourner votre formulaire de contact vers un site tiers.

## Vérifier le champ email dans Airtable

Le plugin suppose que le champ s'appelle `Email`. Si ce n'est pas le cas dans une table donnée, corrigez le nom exact dans la configuration correspondante (**Réglages → Fillout Router → Nom du champ Email**).

## Test avant mise en ligne

- Testez avec un email déjà présent dans Contacts → doit rediriger vers le formulaire Modification, pré-rempli avec le bon `recordId`.
- Testez avec un email absent → doit rediriger vers le formulaire Création.
- Vérifiez que dans Fillout, le formulaire en mode Modification récupère bien les infos existantes du contact via le `recordId` dans l'URL (comportement standard de Fillout, mais à vérifier une fois selon votre configuration).
