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
composer install
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

DATABASE_URL="mysql://utilisateur:motdepasse@127.0.0.1:3306/arkalib?serverVersion=8.0"

MAILER_DSN=smtp://localhost:1025
```

### 4. Créer la base de données et appliquer les migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. Lancer le serveur de développement
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

---

### Fonctionnement

Un utilisateur crée un compte puis rejoint une association — soit en la créant, soit via une invitation. Au sein d'une association, il peut :
- Créer et gérer des budgets
- Organiser les lignes budgétaires en catégories
- Ajouter des transactions (budget réalisé) aux ligne budgetaire afin de comparer le prévisionnel et le réél
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

**Baptiste CHERPIN** — [cherpinb@gmail.com](mailto:cherpinb@gmail.com)
