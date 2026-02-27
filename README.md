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

## 📄 Licence

Ce projet est développé dans le cadre d'un diplôme professionnel de développeur web. Tous droits réservés.

---

## 👤 Auteur

**Baptiste CHERPIN** — [cherpinb@gmail.com](mailto:cherpinb@gmail.com)
