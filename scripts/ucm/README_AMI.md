# Configuration AMI pour UCM6301

## Guide d'installation rapide

### Étape 1 : Copier le script sur le UCM

Sur votre ordinateur :
```bash
scp configure_ami.sh root@<IP_UCM>:/tmp/
```

Exemple :
```bash
scp configure_ami.sh root@192.168.1.100:/tmp/
```

### Étape 2 : Se connecter au UCM via SSH

```bash
ssh root@<IP_UCM>
```

Mot de passe par défaut : `admin` (à changer!)

### Étape 3 : Exécuter le script

```bash
cd /tmp
chmod +x configure_ami.sh

# Option 1 : Configuration automatique avec mot de passe généré
./configure_ami.sh

# Option 2 : Spécifier votre propre mot de passe
./configure_ami.sh "VotreMotDePasseSecurise123!"

# Option 3 : Spécifier mot de passe ET IP du serveur Odoo (recommandé)
./configure_ami.sh "VotreMotDePasseSecurise123!" "192.168.1.50/255.255.255.255"
```

### Étape 4 : Noter les informations de connexion

Le script affichera :
```
Paramètres de connexion AMI:
  - Hôte: 192.168.1.100
  - Port: 5038
  - Utilisateur: odoo
  - Mot de passe: VotreMotDePasseSecurise123!
```

**⚠️ IMPORTANT : Notez ces informations, vous en aurez besoin dans Odoo !**

### Étape 5 : Configurer dans Odoo

1. Allez dans **Grandstream UCM > Configuration**
2. Ouvrez votre configuration UCM
3. Activez **"Remontée de fiche temps réel (AMI)"**
4. Entrez les informations :
   - Port AMI : `5038`
   - Utilisateur AMI : `odoo`
   - Mot de passe AMI : (celui noté à l'étape 4)
5. Cliquez sur **"Tester la connexion AMI"**
6. Si le test réussit, sauvegardez

### Vérification

Sur le UCM, vérifiez que l'AMI fonctionne :
```bash
# Voir les utilisateurs AMI configurés
asterisk -rx "manager show users"

# Voir les connexions actives
asterisk -rx "manager show connected"

# Voir les logs en temps réel
tail -f /var/log/asterisk/messages
```

## Dépannage

### Erreur : "Connection refused"

1. Vérifiez que le port 5038 est ouvert :
```bash
netstat -tuln | grep 5038
```

2. Vérifiez le pare-feu :
```bash
iptables -L -n | grep 5038
```

3. Autorisez le port si nécessaire :
```bash
iptables -A INPUT -p tcp --dport 5038 -j ACCEPT
service iptables save
```

### Erreur : "Authentication failed"

1. Vérifiez le fichier de configuration :
```bash
cat /etc/asterisk/manager.conf
```

2. Vérifiez l'utilisateur et le mot de passe dans Odoo

3. Rechargez la configuration :
```bash
asterisk -rx "manager reload"
```

### Erreur : "Permission denied"

1. Vérifiez l'IP autorisée dans `/etc/asterisk/manager.conf`
2. Modifiez la ligne `permit=` avec l'IP de votre serveur Odoo
3. Rechargez : `asterisk -rx "manager reload"`

### Les appels ne remontent pas

1. Vérifiez que le service est démarré dans Odoo (voir les logs Odoo)
2. Testez la connexion AMI depuis Odoo
3. Vérifiez les logs Asterisk :
```bash
tail -f /var/log/asterisk/messages | grep odoo
```

## Sécurité

### Recommandations

1. **Changez le mot de passe par défaut** :
   ```bash
   ./configure_ami.sh "UnMotDePasseTresComplexe123!@#"
   ```

2. **Limitez l'accès par IP** (recommandé en production) :
   ```bash
   # Autoriser seulement l'IP du serveur Odoo
   ./configure_ami.sh "MonMotDePasse" "192.168.1.50/255.255.255.255"
   ```

3. **Utilisez un pare-feu** :
   ```bash
   # Autoriser seulement depuis l'IP d'Odoo
   iptables -A INPUT -p tcp -s 192.168.1.50 --dport 5038 -j ACCEPT
   iptables -A INPUT -p tcp --dport 5038 -j DROP
   ```

4. **Surveillez les connexions** :
   ```bash
   # Vérifiez régulièrement les connexions
   asterisk -rx "manager show connected"
   ```

## Désinstallation

Pour désactiver l'AMI :

```bash
# Restaurer la sauvegarde
cp /etc/asterisk/manager.conf.backup.* /etc/asterisk/manager.conf

# Ou désactiver complètement
sed -i 's/enabled = yes/enabled = no/' /etc/asterisk/manager.conf

# Recharger
asterisk -rx "manager reload"
```

## Support

En cas de problème :
1. Consultez les logs Asterisk : `tail -f /var/log/asterisk/messages`
2. Consultez les logs Odoo
3. Vérifiez la connectivité réseau : `telnet <IP_UCM> 5038`
