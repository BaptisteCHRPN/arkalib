# ARKALIB
## Introduction
> Arkalib est une application qui permet de créer et de suivre des budgets en toute simplicité sans notion comptable avancée. Cette application est à destination des petites structures telle que des associations.

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.2, Symfony 7.x |
| Templating | Twig |
| Base de données | MySQL 9.1.0 |
| ORM | Doctrine |
| Frontend | Bootstrap / CSS personnalisé |
| Authentification | Symfony Security |
| Emails | Symfony Mailer |

## Pré-requis
Avant d'installer Arkalib, assurez-vous de disposer de :

- **PHP** >= 8.2 avec les extensions : `pdo`, `pdo_mysql`, `intl`, `mbstring`, `xml`, `fileinfo`
- **Composer** >= 2.x
- **MySQL** >= 9.1.0
- **Symfony CLI** (optionnel mais recommandé)

## Installation
### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-utilisateur/arkalib.git
cd arkalib
```

### 2. Installer les dépendances PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurer les variables d'environnement

Copiez le fichier d'exemple et adaptez-le :

```bash
cp .env .env.local
```

Éditez `.env.local` et renseignez au minimum :

```dotenv
APP_ENV=dev
APP_SECRET=votre_secret_ici

# URL publique de l'instance : sert à générer les liens absolus
# (invitation, confirmation d'e-mail) hors contexte HTTP.
DEFAULT_URI=http://localhost:8000

DATABASE_URL="mysql://utilisateur:motdepasse@127.0.0.1:3306/arkalib?serverVersion=8.0"

MAILER_DSN=smtp://localhost:1025
MAILER_FROM_ADDRESS=noreply@example.org
MAILER_FROM_NAME=Arkalib

```

### 4. Créer la base de données et appliquer les migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Compiler les assets
```bash
php bin/console importmap:install
php bin/console asset-map:compile
```

### 6. Gérer le cache
```bash
php bin/console cache:clear
php bin/console cache:warmup
```

### 7. Créer un utilisateur en ligne de commande

La commande `app:create-user` crée un compte de n'importe quel type, sans passer par
le formulaire d'inscription ni par l'e-mail de confirmation :

```bash
php bin/console app:create-user <email> <motdepasse> [vérifié] [rôle]
```

| Argument | Obligatoire | Défaut | Valeurs |
|---|---|---|---|
| email | oui | — | une adresse valide, non déjà utilisée |
| mot de passe | oui | — | en clair, il est haché avant enregistrement |
| vérifié | non | `true` | `true` / `false` |
| rôle | non | `ROLE_USER` | `ROLE_USER`, `ROLE_ADMIN` |

Les deux derniers arguments étant optionnels, cette forme courte crée un membre
standard, vérifié et prêt à se connecter :

```bash
php bin/console app:create-user membre@mondomaine.tld 'MotDePasse123'
```

Et la forme complète permet de choisir le rôle — c'est ainsi que se crée le premier
administrateur, l'inscription publique ne produisant que des comptes standards :

```bash
php bin/console app:create-user admin@mondomaine.tld 'MotDePasse123' true ROLE_ADMIN
```

Le troisième argument correspond au champ `isVerified` de l'entité `User`. À `true`,
le compte est immédiatement utilisable sans confirmation d'adresse, ce qui est utile
tant que le mailer n'est pas configuré. À `false`, le compte est créé mais la
connexion restera bloquée jusqu'à validation de l'e-mail — pratique pour reproduire
ce cas en test.

> Le mot de passe étant passé en clair, il reste inscrit dans l'historique de votre
> terminal. Pensez à le changer après la première connexion sur une instance exposée.

### 8. Lancer le serveur de développement
```bash
# Avec Symfony CLI
symfony serve

# Ou avec PHP built-in
php -S localhost:8000 -t public/
```

L'application est accessible sur [http://localhost:8000](http://localhost:8000).

---

### Envoi d'emails

En développement, il est recommandé d'utiliser **Mailpit** ou **Mailtrap** pour intercepter les emails sans les envoyer réellement. Adaptez `MAILER_DSN` en conséquence.

**Adresse d'expédition.** Tous les e-mails de l'application (invitation, vérification d'adresse, réinitialisation de mot de passe, changement d'adresse) partent de `MAILER_FROM_ADDRESS`, affichée sous le nom `MAILER_FROM_NAME`.

Si vous auto-hébergez Arkalib, **renseignez impérativement une adresse appartenant à votre propre domaine**. Une adresse d'un domaine tiers sera rejetée par la plupart des serveurs destinataires (SPF/DKIM), ou refusée à l'envoi par votre propre serveur SMTP :

```dotenv
MAILER_FROM_ADDRESS=noreply@mondomaine.tld
MAILER_FROM_NAME=Arkalib
```

Pensez également à ajuster `DEFAULT_URI` : les liens contenus dans les e-mails envoyés hors contexte HTTP (tâches planifiées, commandes CLI) en dépendent.

---

### Fonctionnement

Un utilisateur crée un compte puis rejoint une association — soit en la créant, soit via une invitation. Au sein d'une association, il peut :
- Créer et gérer des budgets
- Organiser les lignes budgétaires en catégories
- Ajouter des transactions (budget réalisé) aux lignes budgetaires afin de comparer le prévisionnel et le réel
- Inviter des membres à rejoindre l'association


## Feedback recherché

Je suis développeur junior en reconversion et je cherche des retours honnêtes 
sur ce projet. Les points qui m'intéressent particulièrement :

- **Architecture générale** : j'ai séparé l'application en deux espaces distincts 
  (`member` et `admin`), chacun avec ses propres controllers et templates. 
  Est-ce une approche cohérente ? Que feriez-vous différemment ?
- **Organisation des templates Twig** : je m'y perds parfois moi-même, 
  ce qui est probablement un signal. Tout retour sur la structure est bienvenu.
- **Ce que je ne sais pas que je ne sais pas** : étant junior, mes angles morts 
  sont par définition invisibles pour moi. N'hésitez pas à signaler ce qui vous 
  surprend, même si je n'ai pas pensé à le mentionner.

## Auteur

**Baptiste CHERPIN** — [baptiste@batdev.fr](mailto:baptiste@batdev.fr)
