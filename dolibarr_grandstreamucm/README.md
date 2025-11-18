# Module Grandstream UCM pour Dolibarr

Module d'intégration entre Grandstream UCM et Dolibarr pour la synchronisation des appels téléphoniques et des enregistrements.

## Fonctionnalités

- **Synchronisation automatique des appels** depuis Grandstream UCM vers Dolibarr
- **Remontée de fiche client** : affichage de l'historique d'appels dans la fiche tiers
- **Lecture des enregistrements** : téléchargement des messages vocaux depuis Dolibarr
- **Statistiques d'appels** : temps passé, nombre d'appels, derniers appels
- **Création automatique de tiers** pour les numéros inconnus
- **Notes et commentaires** : ajout de notes sur chaque appel
- **Filtres avancés** : par type d'appel, direction, période

## Prérequis

- **Dolibarr** : Version 14.0 ou ultérieure
- **Grandstream UCM** : Dernière version avec API activée
- **PHP** : 7.4 ou supérieur avec extension cURL

## Installation

### 1. Copier le module

```bash
# Copier le module dans le répertoire des modules personnalisés
cp -r dolibarr_grandstreamucm /path/to/dolibarr/htdocs/custom/grandstreamucm
```

### 2. Activer le module

1. Connectez-vous à Dolibarr en tant qu'administrateur
2. Allez dans **Accueil → Configuration → Modules/Applications**
3. Recherchez **"Grandstream"** dans la liste
4. Cliquez sur le bouton **Activer**

### 3. Configurer les permissions

1. Allez dans **Accueil → Utilisateurs & Groupes → Groupes**
2. Sélectionnez le groupe concerné
3. Activez les permissions Grandstream UCM :
   - Lire les journaux d'appels
   - Créer/Modifier les journaux d'appels
   - Supprimer les journaux d'appels
   - Configurer le module

## Configuration

### 1. Accéder à la configuration

1. Allez dans le menu **GrandstreamUCM**
2. Cliquez sur **Configuration** (ou via Accueil → Configuration → Modules)

### 2. Paramètres de connexion

- **Hôte/IP du UCM** : Adresse IP de votre Grandstream UCM
- **Port API** : 8089 (par défaut)
- **Utiliser HTTPS** : Recommandé pour la sécurité
- **Nom d'utilisateur API** : Identifiant de l'utilisateur API
- **Mot de passe API** : Mot de passe de l'utilisateur API

### 3. Paramètres de synchronisation

- **Intervalle de synchronisation** : Fréquence en minutes (par défaut : 15)
- **Jours à synchroniser** : Nombre de jours d'historique (par défaut : 30)
- **Télécharger les enregistrements** : Activer pour télécharger les fichiers audio
- **Créer automatiquement les contacts** : Créer un tiers pour les numéros inconnus

### 4. Tester la connexion

Cliquez sur **Tester la connexion** pour vérifier que les paramètres sont corrects.

### 5. Lancer la synchronisation

Cliquez sur **Synchroniser maintenant** pour effectuer une première synchronisation.

## Configuration Grandstream UCM

### Activer l'API

1. Connectez-vous à l'interface web du UCM
2. Allez dans **System Settings → API Configuration**
3. Cochez **Enable API**
4. Notez le port (par défaut : 8089)
5. Sauvegardez

### Créer un utilisateur API

1. Allez dans **User Management → Users**
2. Créez un nouvel utilisateur avec les permissions :
   - Accès CDR (Call Detail Records)
   - Accès aux enregistrements d'appels

## Utilisation

### Page d'accueil

Le menu **GrandstreamUCM** affiche :
- Statistiques globales des appels
- Liste des appels récents
- Accès rapide à la configuration

### Liste des appels

Accessible via **GrandstreamUCM → Journaux d'appels** :
- Filtres par direction, type, date
- Recherche par numéro ou nom
- Export possible

### Fiche tiers enrichie

Un nouvel onglet **Appels** apparaît dans les fiches tiers :
- Statistiques d'appels pour ce tiers
- Historique des appels
- Accès aux enregistrements
- Possibilité d'ajouter des notes

### Détail d'un appel

Chaque appel contient :
- Informations de l'appel (date, durée, direction, type)
- Numéros appelant/appelé
- Tiers associé
- Enregistrement audio (si disponible)
- Notes privées et publiques

## Synchronisation automatique

Le module crée une tâche planifiée (cron) qui s'exécute automatiquement :
- Fréquence configurée dans les paramètres
- Synchronise les nouveaux appels
- Crée les tiers pour les numéros inconnus

Pour vérifier les tâches planifiées :
1. Allez dans **Accueil → Configuration → Tâches planifiées**
2. Recherchez **"Sync Grandstream UCM Calls"**

## Structure du module

```
dolibarr_grandstreamucm/
├── admin/
│   └── setup.php              # Page de configuration
├── class/
│   ├── calllog.class.php      # Classe métier journaux d'appels
│   └── grandstreamucm.class.php   # Connecteur API
├── core/modules/
│   └── modGrandstreamUCM.class.php   # Descripteur du module
├── langs/
│   └── fr_FR/
│       └── grandstreamucm.lang   # Traductions françaises
├── lib/
│   └── grandstreamucm.lib.php    # Fonctions utilitaires
├── sql/
│   ├── llx_grandstreamucm_calllog.sql   # Structure table
│   └── llx_grandstreamucm_calllog.key.sql   # Clés étrangères
├── call_card.php              # Détail d'un appel
├── call_list.php              # Liste des appels
├── index.php                  # Page d'accueil
├── thirdparty_calls.php       # Onglet appels dans tiers
└── README.md
```

## API Grandstream UCM

Le module utilise l'API REST du Grandstream UCM :

- `POST /api/login` : Authentification
- `GET /api/cdr` : Récupération des CDR
- `GET /api/recording` : Téléchargement des enregistrements

Documentation : [API Guide Grandstream UCM](http://www.grandstream.com/sites/default/files/Resources/UCM_API_Guide.pdf)

## Dépannage

### Le module ne s'affiche pas

1. Vérifiez que le dossier est bien dans `htdocs/custom/`
2. Vérifiez les permissions du dossier (755)
3. Allez dans Configuration → Modules et cliquez sur "Actualiser"

### Erreur de connexion au UCM

1. Vérifiez l'adresse IP et le port
2. Vérifiez que l'API est activée sur le UCM
3. Testez avec curl :
   ```bash
   curl -k -X POST https://IP:8089/api/login \
     -H "Content-Type: application/json" \
     -d '{"username":"user","password":"pass"}'
   ```

### Les appels ne se synchronisent pas

1. Vérifiez les logs Dolibarr
2. Vérifiez les permissions de l'utilisateur API
3. Exécutez manuellement la synchronisation

### Les enregistrements ne se téléchargent pas

1. Vérifiez que l'option est activée dans la configuration
2. Vérifiez les permissions du dossier `documents/grandstreamucm/recordings`
3. Vérifiez l'espace disque disponible

## Logs

Les logs du module sont enregistrés dans les logs Dolibarr :
- Niveau INFO pour les synchronisations réussies
- Niveau ERROR pour les erreurs

Consulter les logs :
```bash
tail -f /path/to/dolibarr/documents/dolibarr.log | grep grandstream
```

## Sécurité

### Recommandations

- Utilisez HTTPS pour la connexion au UCM
- Créez un utilisateur API dédié avec permissions minimales
- Protégez le dossier des enregistrements
- Changez régulièrement le mot de passe API

### Permissions Dolibarr

Le module définit 4 niveaux de permissions :
- **Lecture** : Voir les journaux d'appels
- **Écriture** : Modifier les appels et ajouter des notes
- **Suppression** : Supprimer des journaux d'appels
- **Administration** : Configurer le module

## Licence

Ce module est distribué sous licence GPL-3.0.

## Support

Pour toute question ou problème :
- Consultez la documentation Dolibarr
- Ouvrez une issue sur GitHub
- Contactez le support Grandstream pour les questions liées à l'API

## Changelog

### Version 1.0.0
- Version initiale
- Synchronisation des appels depuis Grandstream UCM
- Affichage dans les fiches tiers
- Téléchargement des enregistrements
- Création automatique de tiers
- Notes sur les appels
