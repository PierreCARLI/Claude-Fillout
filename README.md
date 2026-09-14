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

## Installation

1. Compressez le dossier `wordpress-plugin/occitanie-angels-fillout-router/` en `.zip`, ou déposez-le via FTP/SFTP OVH dans `wp-content/plugins/`.
2. Dans l'admin WordPress : **Extensions** → activez **Occitanie Angels - Fillout Router**.
3. Allez dans **Réglages → Fillout Router**. Les valeurs suivantes sont déjà pré-remplies :
   - Base Airtable : `appQwliKqpfcSOYb0`
   - Table Contacts : `tblmVqV8aXjNauqBl`
   - Champ email : `Email` *(à corriger si le nom exact du champ dans votre table diffère)*
   - Formulaire Création : `https://occitanieangels.fillout.com/t/tajiPdzR5uus`
   - Formulaire Modification : `https://occitanieangels.fillout.com/t/nAxnHFpKNmus`
   - Paramètre d'URL du Record ID : `id`
4. Renseignez votre **Personal Access Token Airtable** (créé sur [airtable.com/create/tokens](https://airtable.com/create/tokens), scope `data.records:read`, limité à cette base). Deux options :
   - Le saisir directement dans le champ de réglages (stocké en base WordPress), ou
   - (recommandé, plus sûr) l'ajouter dans `wp-config.php` :
     ```php
     define('OA_FILLOUT_AIRTABLE_TOKEN', 'patXXXXXXXXXXXXXX');
     ```
5. Créez (ou éditez) la page WordPress qui servira de page d'accueil, et insérez-y le shortcode :
   ```
   [fillout_router]
   ```
6. Publiez cette page, et c'est **son URL** (pas celles de Fillout) que vous partagez sur LinkedIn et le site.

## Vérifier le champ email dans Airtable

Le plugin suppose que le champ s'appelle `Email` dans la table Contacts. Si ce n'est pas le cas, corrigez le nom exact dans **Réglages → Fillout Router → Nom du champ Email**.

## Test avant mise en ligne

- Testez avec un email déjà présent dans Contacts → doit rediriger vers le formulaire Modification, pré-rempli avec le bon `recordId`.
- Testez avec un email absent → doit rediriger vers le formulaire Création.
- Vérifiez que dans Fillout, le formulaire en mode Modification récupère bien les infos existantes du contact via le `recordId` dans l'URL (comportement standard de Fillout, mais à vérifier une fois selon votre configuration).
