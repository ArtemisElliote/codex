# Application de gestion de stock (PHP + MySQL)

Cette application web simple permet de gérer des produits, suivre les entrées/sorties de stock et conserver un historique des mouvements.

## Prérequis
- PHP 8+
- MySQL 8+

## Installation
1. Créez la base de données et les tables :
   ```sql
   source schema.sql;
   ```
2. Configurez les variables d'environnement (optionnel) :
   - `DB_HOST` (défaut `127.0.0.1`)
   - `DB_NAME` (défaut `stock_manager`)
   - `DB_USER` (défaut `root`)
   - `DB_PASS` (défaut vide)
3. Lancez le serveur PHP local :
   ```bash
   php -S 0.0.0.0:8000
   ```
4. Ouvrez `http://localhost:8000`.

## Fonctionnalités
- Ajouter, modifier et supprimer des produits.
- Enregistrer les entrées/sorties de stock.
- Visualiser le stock actuel et les derniers mouvements.
