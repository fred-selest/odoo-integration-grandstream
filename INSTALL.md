# Guide d'installation - Grandstream UCM Integration

## Table des matières

1. [Prérequis](#prérequis)
2. [Installation du module](#installation-du-module)
3. [Configuration Grandstream UCM](#configuration-grandstream-ucm)
4. [Configuration Odoo](#configuration-odoo)
5. [Vérification](#vérification)
6. [Résolution de problèmes](#résolution-de-problèmes)

## Prérequis

### Logiciels requis

- **Odoo 17.0** ou version ultérieure
- **Grandstream UCM** (dernière version recommandée)
- **Python 3.8+** (inclus avec Odoo)
- Accès réseau entre Odoo et Grandstream UCM

### Permissions requises

- Accès administrateur sur Odoo
- Accès administrateur sur Grandstream UCM
- Possibilité d'installer des modules Odoo

## Installation du module

### Méthode 1 : Installation manuelle

1. **Copier le module dans le répertoire addons d'Odoo**

   ```bash
   # Naviguer vers le répertoire des addons
   cd /opt/odoo/addons

   # Ou pour une installation avec virtualenv
   cd /path/to/your/odoo/addons

   # Copier le module
   sudo cp -r /path/to/grandstream_ucm_integration .

   # Définir les permissions appropriées
   sudo chown -R odoo:odoo grandstream_ucm_integration
   ```

2. **Redémarrer le service Odoo**

   ```bash
   # Pour systemd
   sudo systemctl restart odoo

   # Ou si vous utilisez le script directement
   sudo service odoo restart
   ```

3. **Mettre à jour la liste des applications dans Odoo**

   - Connectez-vous à Odoo en tant qu'administrateur
   - Allez dans **Applications**
   - Cliquez sur le menu (3 points) en haut à droite
   - Sélectionnez **Mettre à jour la liste des applications**
   - Confirmez l'action

4. **Installer le module**

   - Dans **Applications**, recherchez "Grandstream"
   - Cliquez sur **Installer** sur le module "Grandstream UCM Integration"

### Méthode 2 : Installation en ligne de commande

```bash
# Depuis le répertoire racine d'Odoo
./odoo-bin -u grandstream_ucm_integration -d votre_base_de_donnees --addons-path=/path/to/addons

# Ou avec pip pour les dépendances Python si nécessaire
pip install -r requirements.txt
```

### Méthode 3 : Installation Docker (si applicable)

Si vous utilisez Odoo dans Docker :

```dockerfile
# Dans votre Dockerfile
COPY grandstream_ucm_integration /mnt/extra-addons/grandstream_ucm_integration

# Ou via docker-compose.yml
volumes:
  - ./grandstream_ucm_integration:/mnt/extra-addons/grandstream_ucm_integration
```

## Configuration Grandstream UCM

### 1. Activer l'API

1. Connectez-vous à l'interface web de votre UCM
2. Allez dans **System Settings → API Configuration**
3. Cochez **Enable API**
4. Notez le **Port API** (par défaut : 8089)
5. Cliquez sur **Apply** puis **Save**

### 2. Créer un utilisateur API

1. Allez dans **User Management → Users**
2. Cliquez sur **Add New User**
3. Remplissez les informations :
   - **Extension** : Laissez vide ou attribuez une extension
   - **User Name** : `odoo_api` (ou le nom de votre choix)
   - **Password** : Choisissez un mot de passe fort
   - **User Level** : Admin (ou personnalisez les permissions)

4. Dans l'onglet **Permissions**, assurez-vous que l'utilisateur a accès à :
   - CDR (Call Detail Records)
   - Call Recordings
   - API Access

5. Cliquez sur **Save**

### 3. Configurer les enregistrements d'appels (optionnel)

Si vous souhaitez synchroniser les enregistrements :

1. Allez dans **Call Features → Call Recording**
2. Activez l'enregistrement pour les extensions/trunks souhaités
3. Configurez le format d'enregistrement (WAV recommandé)
4. Sauvegardez les paramètres

### 4. Tester l'accès API

Vous pouvez tester l'API avec curl :

```bash
curl -k -X POST https://votre-ucm-ip:8089/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"odoo_api","password":"votre_mot_de_passe"}'
```

Vous devriez recevoir une réponse avec un cookie de session.

## Configuration Odoo

### 1. Accéder à la configuration

1. Dans Odoo, allez dans **Grandstream → Configuration → UCM Servers**
2. Cliquez sur **Créer**

### 2. Paramètres de connexion

Remplissez les informations suivantes :

- **Nom UCM** : Nom descriptif (ex: "Bureau Principal")
- **Hôte/IP** : Adresse IP de votre UCM (ex: 192.168.1.100)
- **Port** : 8089 (ou le port configuré)
- **Utiliser HTTPS** : ✓ Coché (recommandé)
- **Nom d'utilisateur** : `odoo_api` (l'utilisateur créé précédemment)
- **Mot de passe** : Le mot de passe de l'utilisateur API

### 3. Paramètres de synchronisation

- **Intervalle de sync (minutes)** : 15 (recommandé)
- **Jours à synchroniser** : 30 (ajustez selon vos besoins)
- **Télécharger les enregistrements** : ✓ Coché (si souhaité)

### 4. Paramètres des contacts

- **Créer automatiquement les contacts** : ✓ Coché (recommandé)
- **Pays par défaut** : Sélectionnez votre pays pour le formatage des numéros

### 5. Tester la connexion

1. Cliquez sur le bouton **Tester la connexion**
2. Vous devriez voir un message de succès
3. Si erreur, vérifiez :
   - L'adresse IP et le port
   - Les identifiants
   - La connectivité réseau
   - Les logs Odoo

### 6. Lancer la première synchronisation

1. Cliquez sur **Synchroniser maintenant**
2. Attendez quelques instants
3. Allez dans **Grandstream → Journaux d'appels** pour voir les appels synchronisés

## Vérification

### 1. Vérifier les journaux d'appels

```bash
# Consulter les logs Odoo
tail -f /var/log/odoo/odoo-server.log | grep grandstream
```

Vous devriez voir des messages comme :
```
INFO grandstream: Starting call sync for Bureau Principal
INFO grandstream: Synced 150 calls from Bureau Principal
```

### 2. Vérifier dans l'interface

1. Allez dans **Grandstream → Journaux d'appels**
2. Vérifiez que les appels apparaissent
3. Ouvrez un contact existant
4. Vérifiez l'onglet **Appels** (si le contact a des appels)

### 3. Tester un enregistrement

1. Ouvrez un appel avec enregistrement
2. Cliquez sur le fichier audio
3. Vérifiez qu'il se télécharge/joue correctement

## Résolution de problèmes

### Problème : Module non visible dans la liste

**Solution :**
```bash
# Vérifier les permissions
ls -la /opt/odoo/addons/grandstream_ucm_integration

# Les permissions doivent être :
drwxr-xr-x odoo odoo

# Redémarrer Odoo
sudo systemctl restart odoo

# Mettre à jour la liste des apps dans Odoo
```

### Problème : Erreur d'importation Python

**Solution :**
```bash
# Installer la dépendance manquante
pip3 install requests

# Ou dans un virtualenv
source /path/to/venv/bin/activate
pip install requests
```

### Problème : Connexion refusée au UCM

**Solutions :**
1. Vérifier que l'API est activée sur le UCM
2. Tester avec curl (voir section 3.4)
3. Vérifier le firewall :
   ```bash
   # Sur le serveur UCM ou entre Odoo et UCM
   sudo ufw allow 8089/tcp
   ```
4. Vérifier les certificats SSL si HTTPS

### Problème : Pas d'appels synchronisés

**Solutions :**
1. Vérifier les dates : le module synchronise les X derniers jours
2. Vérifier les permissions de l'utilisateur API
3. Consulter les logs :
   ```bash
   grep -i "grandstream\|call" /var/log/odoo/odoo-server.log
   ```
4. Vérifier que des appels existent dans le UCM pour la période

### Problème : Enregistrements non téléchargés

**Solutions :**
1. Vérifier que les enregistrements sont activés sur le UCM
2. Vérifier les permissions de l'utilisateur API
3. Vérifier l'espace disque sur le serveur Odoo
4. Vérifier les permissions du dossier filestore d'Odoo

### Problème : Erreur 403 lors de la connexion

**Solution :**
```bash
# L'utilisateur API n'a pas les bonnes permissions
# Sur le UCM, vérifier les permissions de l'utilisateur
# Donner accès complet API ou au minimum CDR + Recordings
```

## Support et ressources

### Logs Odoo

Localisation par défaut : `/var/log/odoo/odoo-server.log`

Pour activer le mode debug :
```bash
# Dans odoo.conf
log_level = debug
```

### Logs Grandstream

Sur le UCM, téléchargez les logs système depuis :
**Maintenance → Syslog → Download**

### Documentation

- [Documentation Odoo](https://www.odoo.com/documentation/17.0/)
- [Documentation Grandstream UCM](https://www.grandstream.com/support/ucm)
- [API Grandstream](http://www.grandstream.com/sites/default/files/Resources/UCM_API_Guide.pdf)

### Contact

Pour tout problème non résolu :
1. Consultez les issues GitHub du projet
2. Ouvrez une nouvelle issue avec :
   - Version d'Odoo
   - Modèle de Grandstream UCM
   - Logs d'erreur
   - Étapes pour reproduire le problème

---

**Bonne installation ! 🚀**
