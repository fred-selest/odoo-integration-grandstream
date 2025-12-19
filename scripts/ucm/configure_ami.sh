#!/bin/bash
# Script de configuration AMI pour Grandstream UCM6301
# À exécuter sur le UCM via SSH

set -e

echo "=================================================="
echo "Configuration AMI pour Grandstream UCM6301"
echo "=================================================="
echo ""

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then
    echo "ERREUR: Ce script doit être exécuté en tant que root"
    echo "Utilisez: sudo $0"
    exit 1
fi

# Configuration
AMI_USER="odoo"
AMI_PASSWORD="${1:-OdooAMI2024!}"
ODOO_SERVER_IP="${2:-0.0.0.0/0.0.0.0}"

echo "Configuration:"
echo "  - Utilisateur AMI: $AMI_USER"
echo "  - Mot de passe: ********"
echo "  - IP autorisée: $ODOO_SERVER_IP"
echo ""

# Backup du fichier manager.conf existant
MANAGER_CONF="/etc/asterisk/manager.conf"
if [ -f "$MANAGER_CONF" ]; then
    echo "Sauvegarde de la configuration existante..."
    cp "$MANAGER_CONF" "${MANAGER_CONF}.backup.$(date +%Y%m%d_%H%M%S)"
    echo "✓ Sauvegarde créée"
fi

# Créer/mettre à jour manager.conf
echo ""
echo "Configuration de l'AMI..."

cat > "$MANAGER_CONF" << EOF
;
; AMI - Asterisk Manager Interface
; Configuration pour intégration Odoo/Dolibarr
;

[general]
enabled = yes
port = 5038
bindaddr = 0.0.0.0

; Sécurité
webenabled = no
httptimeout = 60

; Utilisateur pour Odoo/Dolibarr
[$AMI_USER]
secret = $AMI_PASSWORD
deny=0.0.0.0/0.0.0.0
permit=$ODOO_SERVER_IP
read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan,originate
write = system,call,log,verbose,agent,user,config,originate,command
writetimeout = 5000
EOF

echo "✓ Fichier $MANAGER_CONF créé"

# Définir les bonnes permissions
chown asterisk:asterisk "$MANAGER_CONF"
chmod 640 "$MANAGER_CONF"
echo "✓ Permissions configurées"

# Vérifier la syntaxe
echo ""
echo "Vérification de la configuration..."
if asterisk -rx "manager show commands" > /dev/null 2>&1; then
    echo "✓ Configuration valide"
else
    echo "⚠ Impossible de vérifier (Asterisk peut-être arrêté)"
fi

# Recharger la configuration AMI
echo ""
echo "Rechargement de la configuration AMI..."
if asterisk -rx "manager reload" > /dev/null 2>&1; then
    echo "✓ Configuration rechargée"
else
    echo "⚠ Échec du rechargement, redémarrage d'Asterisk..."
    /etc/init.d/asterisk restart
    sleep 5
    echo "✓ Asterisk redémarré"
fi

# Vérifier que l'AMI est accessible
echo ""
echo "Vérification de l'AMI..."
if netstat -tuln | grep -q ":5038"; then
    echo "✓ AMI écoute sur le port 5038"
else
    echo "✗ ERREUR: AMI n'écoute pas sur le port 5038"
    exit 1
fi

# Afficher les informations de connexion
echo ""
echo "=================================================="
echo "Configuration AMI terminée avec succès!"
echo "=================================================="
echo ""
echo "Paramètres de connexion AMI:"
echo "  - Hôte: $(hostname -I | awk '{print $1}')"
echo "  - Port: 5038"
echo "  - Utilisateur: $AMI_USER"
echo "  - Mot de passe: $AMI_PASSWORD"
echo ""
echo "À configurer dans Odoo:"
echo "  1. Allez dans Grandstream UCM > Configuration"
echo "  2. Activez 'Remontée de fiche temps réel'"
echo "  3. Entrez ces informations de connexion AMI"
echo ""
echo "Commandes utiles:"
echo "  - Vérifier le statut: asterisk -rx 'manager show users'"
echo "  - Voir les connexions: asterisk -rx 'manager show connected'"
echo "  - Logs AMI: tail -f /var/log/asterisk/messages"
echo ""

# Test de connexion (optionnel)
read -p "Voulez-vous tester la connexion AMI maintenant? (o/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[OoYy]$ ]]; then
    echo ""
    echo "Test de connexion AMI..."

    # Créer un script de test temporaire
    cat > /tmp/ami_test.expect << 'EXPECTEOF'
#!/usr/bin/expect -f
set timeout 10
set ami_user [lindex $argv 0]
set ami_pass [lindex $argv 1]

spawn telnet localhost 5038
expect "Asterisk Call Manager"
send "Action: Login\r\n"
send "Username: $ami_user\r\n"
send "Secret: $ami_pass\r\n\r\n"
expect "Success"
send "Action: Logoff\r\n\r\n"
expect eof
EXPECTEOF

    if command -v expect &> /dev/null; then
        chmod +x /tmp/ami_test.expect
        if /tmp/ami_test.expect "$AMI_USER" "$AMI_PASSWORD" 2>&1 | grep -q "Success"; then
            echo "✓ Test de connexion réussi!"
        else
            echo "✗ Test de connexion échoué"
        fi
        rm -f /tmp/ami_test.expect
    else
        echo "⚠ 'expect' non installé, test ignoré"
        echo "  Vous pouvez tester manuellement avec:"
        echo "  telnet localhost 5038"
    fi
fi

echo ""
echo "Configuration terminée!"
