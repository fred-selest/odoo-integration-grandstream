# Grandstream UCM Integration pour Odoo

Module d'intégration entre Grandstream UCM et Odoo pour la synchronisation des appels téléphoniques et des enregistrements.

## 🎯 Fonctionnalités

- ✅ **Synchronisation automatique des appels** depuis Grandstream UCM vers Odoo
- ✅ **Remontée de fiche contact** : affichage de l'historique d'appels directement dans la fiche contact
- ✅ **Lecture des enregistrements** : écoute des messages vocaux directement depuis Odoo
- ✅ **Statistiques d'appels** : temps passé, nombre d'appels, derniers appels
- ✅ **Création automatique de contacts** pour les numéros inconnus
- ✅ **Filtres avancés** : par type d'appel, direction, contact, période
- ✅ **Multi-UCM** : support de plusieurs serveurs Grandstream UCM

## 📋 Prérequis

- **Odoo** : Version 17.0 ou ultérieure
- **Grandstream UCM** : Dernière version avec API activée
- **Python** : Bibliothèque `requests` (incluse dans Odoo)
- **Accès réseau** : Connexion entre Odoo et Grandstream UCM

## 📦 Installation

### 1. Installation du module

```bash
# Copier le module dans le répertoire addons d'Odoo
cp -r grandstream_ucm_integration /path/to/odoo/addons/

# Redémarrer le service Odoo
sudo systemctl restart odoo

# Ou en ligne de commande
./odoo-bin -u grandstream_ucm_integration -d your_database
```

### 2. Activation dans Odoo

1. Aller dans **Applications**
2. Retirer le filtre "Applications"
3. Rechercher **"Grandstream UCM Integration"**
4. Cliquer sur **Installer**

## ⚙️ Configuration

### 1. Configuration du serveur UCM

1. Aller dans **Grandstream → Configuration → UCM Servers**
2. Cliquer sur **Créer**
3. Remplir les informations :
   - **Nom UCM** : Nom descriptif (ex: "Serveur Principal")
   - **Hôte/IP** : Adresse IP ou nom d'hôte du UCM
   - **Port** : Port API (par défaut : 8089)
   - **HTTPS** : Cocher si utilisation de HTTPS
   - **Nom d'utilisateur** : Identifiant API
   - **Mot de passe** : Mot de passe API

4. Configurer les options de synchronisation :
   - **Intervalle de sync** : Fréquence en minutes (par défaut : 15)
   - **Jours à synchroniser** : Historique à récupérer (par défaut : 30)
   - **Télécharger les enregistrements** : Activer/désactiver

5. Configurer les contacts :
   - **Créer automatiquement les contacts** : Activer pour créer des contacts pour les numéros inconnus
   - **Pays par défaut** : Pour le formatage des numéros

6. Cliquer sur **Tester la connexion** pour vérifier la configuration

### 2. Configuration API sur Grandstream UCM

Sur votre Grandstream UCM, activez l'accès API :

1. Se connecter à l'interface web du UCM
2. Aller dans **System Settings → API Configuration**
3. Activer **Enable API**
4. Créer un utilisateur API avec les permissions nécessaires :
   - Lecture des CDR (Call Detail Records)
   - Accès aux enregistrements d'appels

## 🚀 Utilisation

### Synchronisation des appels

#### Automatique
- La synchronisation se fait automatiquement toutes les X minutes (configuré dans le serveur UCM)
- Vérifier la dernière synchronisation dans **Configuration → UCM Servers**

#### Manuelle
1. Aller dans **Grandstream → Configuration → UCM Servers**
2. Sélectionner le serveur
3. Cliquer sur **Synchroniser maintenant**

### Consultation des appels

#### Depuis le menu principal
1. Aller dans **Grandstream → Journaux d'appels**
2. Utiliser les filtres :
   - Par direction (entrant/sortant)
   - Par type (répondu/manqué/messagerie)
   - Par période (aujourd'hui/7 jours/30 jours)
   - Par contact

#### Depuis la fiche contact
1. Ouvrir un contact dans **Contacts**
2. L'onglet **Appels** affiche :
   - Statistiques : nombre total, entrants, sortants, manqués
   - Temps de conversation total
   - Dernière date d'appel
   - Liste des appels récents avec enregistrements

### Écoute des enregistrements

#### Depuis la fiche contact
- Cliquer sur l'icône de lecture à côté de chaque appel
- Le fichier audio se télécharge automatiquement

#### Depuis le journal d'appels
- Ouvrir le détail d'un appel
- Section **Enregistrement de l'appel**
- Cliquer sur le fichier pour l'écouter ou le télécharger

## 📊 Fonctionnalités détaillées

### Statistiques par contact

Pour chaque contact, vous pouvez voir :
- **Nombre total d'appels**
- **Appels entrants**
- **Appels sortants**
- **Appels manqués**
- **Appels répondus**
- **Temps de conversation total** (formaté en heures/minutes/secondes)
- **Date du dernier appel**

### Informations d'appel

Chaque enregistrement d'appel contient :
- Date et heure
- Direction (entrant/sortant/interne)
- Type (répondu/manqué/messagerie/occupé)
- Numéro appelant/appelé
- Nom appelant/appelé
- Durée totale et durée de conversation
- Extension et trunk utilisés
- Disposition (statut de l'appel)
- Enregistrement audio (si disponible)
- Notes personnalisées

### Création automatique de contacts

Lorsqu'un appel provient d'un numéro inconnu :
1. Le module vérifie s'il existe un contact avec ce numéro
2. Si aucun contact n'existe et l'option est activée :
   - Un nouveau contact est créé automatiquement
   - Le numéro est utilisé comme nom (ou le nom de l'appelant si disponible)
   - Une note indique que le contact a été créé automatiquement
3. L'appel est lié au contact (nouveau ou existant)

## 🔧 Configuration avancée

### Personnalisation de l'intervalle de synchronisation

Modifier le cron dans **Paramètres → Technique → Actions planifiées** :
- Rechercher **"Grandstream: Sync Call Logs"**
- Modifier l'intervalle selon vos besoins

### Modification de la période de conservation

Par défaut, le module synchronise les 30 derniers jours. Pour modifier :
1. Aller dans la configuration du serveur UCM
2. Modifier **Jours à synchroniser**

## 🐛 Dépannage

### La synchronisation ne fonctionne pas

1. **Vérifier la connexion** :
   - Tester la connexion depuis la configuration UCM
   - Vérifier que le serveur UCM est accessible depuis Odoo

2. **Vérifier les logs Odoo** :
   ```bash
   tail -f /var/log/odoo/odoo-server.log | grep grandstream
   ```

3. **Vérifier les permissions API** :
   - L'utilisateur API doit avoir accès aux CDR et enregistrements

### Les enregistrements ne se téléchargent pas

1. Vérifier que **Télécharger les enregistrements** est activé dans la configuration
2. Vérifier que les enregistrements sont activés sur le UCM
3. Vérifier les permissions de l'utilisateur API

### Les contacts ne sont pas créés automatiquement

1. Vérifier que **Créer automatiquement les contacts** est activé
2. Vérifier que les numéros sont correctement formatés
3. Vérifier les logs pour d'éventuelles erreurs

### Problèmes de format de numéro

1. Configurer le **Pays par défaut** dans la configuration UCM
2. Les numéros sont normalisés automatiquement (suppression des espaces, tirets, etc.)

## 🔒 Sécurité

### Droits d'accès

Le module définit deux niveaux d'accès :

1. **Utilisateur** (base.group_user) :
   - Lecture des configurations UCM
   - Lecture, écriture et création des journaux d'appels
   - Consultation des appels dans les fiches contacts

2. **Administrateur** (base.group_system) :
   - Tous les droits utilisateur
   - Création/modification/suppression des configurations UCM
   - Suppression des journaux d'appels

### Recommandations

- Utiliser HTTPS pour la connexion au UCM
- Créer un utilisateur API dédié avec permissions minimales
- Changer régulièrement le mot de passe API
- Limiter l'accès réseau entre Odoo et UCM (firewall)

## 📝 API Grandstream UCM

Ce module utilise l'API REST du Grandstream UCM. Endpoints utilisés :

- `POST /api/login` : Authentification
- `GET /api/cdr` : Récupération des CDR (Call Detail Records)
- `GET /api/recording` : Téléchargement des enregistrements

Référence : [Documentation API Grandstream UCM](http://www.grandstream.com/sites/default/files/Resources/UCM_API_Guide.pdf)

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Fork le projet
2. Créer une branche (`git checkout -b feature/amelioration`)
3. Commit les changements (`git commit -m 'Ajout fonctionnalité'`)
4. Push vers la branche (`git push origin feature/amelioration`)
5. Ouvrir une Pull Request

## 📄 Licence

Ce module est distribué sous licence LGPL-3.

## 📞 Support

Pour toute question ou problème :
- Ouvrir une issue sur GitHub
- Consulter la documentation Odoo
- Contacter le support Grandstream pour les questions liées à l'API

## 🎯 Roadmap

Fonctionnalités prévues :
- [ ] Support des webhooks pour synchronisation en temps réel
- [ ] Statistiques avancées et tableaux de bord
- [ ] Notifications pour appels manqués
- [ ] Intégration avec le module CRM d'Odoo
- [ ] Support multi-langue (EN, FR, ES, DE)
- [ ] Export des statistiques en PDF/Excel
- [ ] Gestion des campagnes d'appels

## 📚 Ressources

- [Documentation Odoo 17](https://www.odoo.com/documentation/17.0/)
- [Documentation Grandstream UCM](https://www.grandstream.com/support/ucm)
- [API Grandstream](http://www.grandstream.com/sites/default/files/Resources/UCM_API_Guide.pdf)