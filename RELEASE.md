# Guide de Release et Mises à Jour

Ce document explique comment créer des releases et comment fonctionne le système de mise à jour automatique.

## Système de Versioning

Le projet utilise [Semantic Versioning](https://semver.org/) (SemVer) :
- **MAJOR.MINOR.PATCH** (ex: 1.2.3)
- **MAJOR** : Changements incompatibles avec les versions précédentes
- **MINOR** : Nouvelles fonctionnalités rétrocompatibles
- **PATCH** : Corrections de bugs rétrocompatibles

## Créer une Release

### Méthode automatique (recommandée)

1. **Mettre à jour le CHANGELOG.md**

   Ajoutez une section pour la nouvelle version :
   ```markdown
   ## [1.1.0] - 2024-02-01

   ### Added
   - Nouvelle fonctionnalité X
   - Amélioration Y

   ### Fixed
   - Correction du bug Z
   ```

2. **Exécuter le script de release**

   ```bash
   chmod +x scripts/release.sh
   ./scripts/release.sh 1.1.0
   ```

   Le script va :
   - Mettre à jour le fichier VERSION
   - Mettre à jour les versions dans les modules Odoo et Dolibarr
   - Commiter les changements
   - Créer un tag Git
   - Pousser sur GitHub

3. **GitHub Actions crée automatiquement la release**

   Le workflow `.github/workflows/release.yml` :
   - Crée les packages ZIP pour chaque module
   - Extrait les notes de version du CHANGELOG
   - Publie la release sur GitHub

### Méthode manuelle

1. Mettre à jour VERSION, CHANGELOG et les versions dans les modules
2. Commiter : `git commit -m "Release v1.1.0"`
3. Créer le tag : `git tag -a v1.1.0 -m "Release 1.1.0"`
4. Pousser : `git push origin main && git push origin v1.1.0`

### Créer des packages localement

```bash
chmod +x scripts/build.sh
./scripts/build.sh
```

Les packages seront créés dans le dossier `dist/`.

## Système de Mise à Jour Automatique

### Module Odoo

Le module Odoo inclut un gestionnaire de mises à jour accessible via :
**GrandstreamUCM → Configuration → Vérifier les mises à jour**

**Fonctionnalités :**
- Vérifie les nouvelles versions sur GitHub
- Affiche les notes de version
- Télécharge et installe automatiquement les mises à jour
- Crée une sauvegarde avant installation
- Tâche planifiée pour vérification quotidienne
- Notifications aux administrateurs

**Utilisation :**
1. Aller dans GrandstreamUCM → Configuration → Vérifier les mises à jour
2. Cliquer sur "Vérifier les mises à jour"
3. Si une mise à jour est disponible, cliquer sur "Télécharger et installer"
4. Redémarrer Odoo pour appliquer les changements

**Vérification automatique :**
- Le cron `Grandstream: Check for Updates` s'exécute quotidiennement
- Les administrateurs reçoivent une notification si une mise à jour est disponible

### Module Dolibarr

Le module Dolibarr inclut une page de mise à jour accessible via :
**Configuration → Mises à jour**

**Fonctionnalités :**
- Vérifie les nouvelles versions sur GitHub
- Affiche les notes de version
- Télécharge et installe les mises à jour
- Crée une sauvegarde avant installation
- Tâche planifiée pour vérification quotidienne

**Utilisation :**
1. Aller dans GrandstreamUCM → Configuration (onglet Mises à jour)
2. Cliquer sur "Vérifier les mises à jour"
3. Si disponible, cliquer sur "Télécharger et installer la mise à jour"
4. Vider le cache Dolibarr et recharger

## Structure des Releases GitHub

Chaque release contient 3 packages :

1. **grandstream_ucm_integration_odoo_vX.X.X.zip**
   - Module Odoo seul
   - Prêt à être déployé dans le dossier addons

2. **grandstream_ucm_dolibarr_vX.X.X.zip**
   - Module Dolibarr seul
   - Prêt à être déployé dans htdocs/custom/

3. **grandstream_ucm_all_vX.X.X.zip**
   - Les deux modules + documentation
   - Pour les déploiements complets

## API GitHub pour les mises à jour

Les modules utilisent l'API GitHub pour vérifier les mises à jour :

```
GET https://api.github.com/repos/fred-selest/odoo-integration-grandstream/releases/latest
```

**Réponse attendue :**
```json
{
  "tag_name": "v1.1.0",
  "published_at": "2024-02-01T10:00:00Z",
  "body": "Release notes...",
  "assets": [
    {
      "name": "grandstream_ucm_all_v1.1.0.zip",
      "browser_download_url": "https://..."
    }
  ]
}
```

## Dépannage

### La mise à jour échoue

1. **Vérifier les permissions**
   - Le dossier du module doit être accessible en écriture

2. **Vérifier la connexion**
   - Accès à api.github.com requis
   - Proxy/firewall peut bloquer

3. **Restaurer la sauvegarde**
   - En cas d'échec, la sauvegarde est automatiquement restaurée
   - Chercher les dossiers `*_backup_*`

### Les mises à jour ne sont pas détectées

1. Vérifier que des releases existent sur GitHub
2. Vérifier le format du tag (doit être `vX.X.X`)
3. Consulter les logs pour les erreurs

### Mise à jour manuelle

Si la mise à jour automatique échoue :

1. Télécharger le package depuis GitHub Releases
2. Sauvegarder le module actuel
3. Extraire le nouveau package
4. Remplacer les fichiers
5. Redémarrer Odoo/Dolibarr

## Best Practices

### Avant une release

- [ ] Tester toutes les fonctionnalités
- [ ] Mettre à jour le CHANGELOG
- [ ] Vérifier la compatibilité avec les versions supportées
- [ ] Documenter les changements breaking

### Versioning

- Patch (x.x.X) : Corrections de bugs uniquement
- Minor (x.X.0) : Nouvelles fonctionnalités
- Major (X.0.0) : Changements breaking

### CHANGELOG

Suivre le format [Keep a Changelog](https://keepachangelog.com/) :
- Added : Nouvelles fonctionnalités
- Changed : Changements de fonctionnalités existantes
- Deprecated : Fonctionnalités qui seront supprimées
- Removed : Fonctionnalités supprimées
- Fixed : Corrections de bugs
- Security : Corrections de sécurité

## Fichiers de configuration

| Fichier | Description |
|---------|-------------|
| `VERSION` | Version actuelle du projet |
| `CHANGELOG.md` | Historique des changements |
| `.github/workflows/release.yml` | Workflow de release automatique |
| `scripts/release.sh` | Script de release |
| `scripts/build.sh` | Script de build local |

## Support

Pour les problèmes liés aux releases et mises à jour :
- Ouvrir une issue sur GitHub
- Inclure la version actuelle et les logs d'erreur
