<?php

declare(strict_types=1);

$db = require __DIR__ . '/db.php';
$pdo = $db['pdo'];
$dbError = $db['error'];

$errors = [];
$success = null;
$products = [];
$moves = [];

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

if ($dbError) {
    $errors[] = 'Connexion à la base de données impossible. Vérifiez les paramètres et importez schema.sql.';
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        $name = sanitize($_POST['name'] ?? '');
        $sku = sanitize($_POST['sku'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $location = sanitize($_POST['location'] ?? '');

        if ($name === '' || $sku === '') {
            $errors[] = 'Le nom et le SKU sont obligatoires.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO products (name, sku, quantity, location) VALUES (:name, :sku, :quantity, :location)');
            $stmt->execute([
                'name' => $name,
                'sku' => $sku,
                'quantity' => $quantity,
                'location' => $location !== '' ? $location : null,
            ]);
            $success = 'Produit ajouté avec succès.';
        }
    }

    if ($action === 'update_product') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $sku = sanitize($_POST['sku'] ?? '');
        $location = sanitize($_POST['location'] ?? '');

        if ($id <= 0 || $name === '' || $sku === '') {
            $errors[] = 'Veuillez renseigner tous les champs requis.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE products SET name = :name, sku = :sku, location = :location WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'sku' => $sku,
                'location' => $location !== '' ? $location : null,
                'id' => $id,
            ]);
            $success = 'Produit mis à jour.';
        }
    }

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $success = 'Produit supprimé.';
        }
    }

    if ($action === 'add_move') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $movement = $_POST['movement_type'] ?? 'in';
        $quantity = (int)($_POST['move_quantity'] ?? 0);
        $note = sanitize($_POST['note'] ?? '');

        if ($productId <= 0 || !in_array($movement, ['in', 'out'], true) || $quantity <= 0) {
            $errors[] = 'Veuillez renseigner un mouvement valide.';
        }

        if (!$errors) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT quantity FROM products WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch();

            if (!$product) {
                $pdo->rollBack();
                $errors[] = 'Produit introuvable.';
            } else {
                $currentQty = (int)$product['quantity'];
                $newQty = $movement === 'in' ? $currentQty + $quantity : $currentQty - $quantity;

                if ($newQty < 0) {
                    $pdo->rollBack();
                    $errors[] = 'Stock insuffisant pour cette sortie.';
                } else {
                    $insertMove = $pdo->prepare('INSERT INTO stock_moves (product_id, movement_type, quantity, note) VALUES (:product_id, :movement_type, :quantity, :note)');
                    $insertMove->execute([
                        'product_id' => $productId,
                        'movement_type' => $movement,
                        'quantity' => $quantity,
                        'note' => $note !== '' ? $note : null,
                    ]);

                    $update = $pdo->prepare('UPDATE products SET quantity = :quantity WHERE id = :id');
                    $update->execute([
                        'quantity' => $newQty,
                        'id' => $productId,
                    ]);

                    $pdo->commit();
                    $success = 'Mouvement enregistré.';
                }
            }
        }
    }
}

if ($pdo) {
    $products = $pdo->query('SELECT * FROM products ORDER BY updated_at DESC')->fetchAll();
    $moves = $pdo->query('SELECT stock_moves.*, products.name AS product_name FROM stock_moves JOIN products ON products.id = stock_moves.product_id ORDER BY stock_moves.created_at DESC LIMIT 8')->fetchAll();
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de stock</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Inter", "Segoe UI", sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        body {
            margin: 0;
            padding: 32px;
        }

        h1, h2 {
            margin: 0 0 12px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 18px rgba(31, 41, 55, 0.08);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
        }

        label {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }

        input, select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        button {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        button.secondary {
            background: #9ca3af;
        }

        button.danger {
            background: #dc2626;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .pill.in {
            background: #dcfce7;
            color: #166534;
        }

        .pill.out {
            background: #fee2e2;
            color: #991b1b;
        }

        .inline-form {
            display: inline;
        }

        .muted {
            color: #6b7280;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<div class="container">
    <div>
        <h1>Gestion de stock</h1>
        <p class="muted">Suivez vos produits, enregistrez les entrées et sorties, et contrôlez les niveaux en temps réel.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>Nouveau produit</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_product">
                <label for="name">Nom du produit</label>
                <input id="name" name="name" required>

                <label for="sku">SKU</label>
                <input id="sku" name="sku" required>

                <label for="quantity">Quantité initiale</label>
                <input id="quantity" name="quantity" type="number" min="0" value="0">

                <label for="location">Emplacement</label>
                <input id="location" name="location" placeholder="Ex: Entrepôt A">

                <button type="submit" <?= $pdo ? '' : 'disabled' ?>>Ajouter</button>
            </form>
        </div>

        <div class="card">
            <h2>Enregistrer un mouvement</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_move">

                <label for="product_id">Produit</label>
                <select id="product_id" name="product_id" required>
                    <option value="">Sélectionner</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id'] ?>">
                            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="movement_type">Type</label>
                <select id="movement_type" name="movement_type" required>
                    <option value="in">Entrée</option>
                    <option value="out">Sortie</option>
                </select>

                <label for="move_quantity">Quantité</label>
                <input id="move_quantity" name="move_quantity" type="number" min="1" required>

                <label for="note">Note</label>
                <input id="note" name="note" placeholder="Référence ou commentaire">

                <button type="submit" <?= $pdo ? '' : 'disabled' ?>>Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>Produits en stock</h2>
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>SKU</th>
                    <th>Quantité</th>
                    <th>Emplacement</th>
                    <th>Dernière mise à jour</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$products): ?>
                    <tr>
                        <td colspan="6" class="muted">Ajoutez un produit pour commencer.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$product['quantity'] ?></td>
                        <td><?= htmlspecialchars($product['location'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($product['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form class="inline-form" method="POST">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                <button class="danger" type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_product">
                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                <div class="grid">
                                    <div>
                                        <label>Nom</label>
                                        <input name="name" value="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div>
                                        <label>SKU</label>
                                        <input name="sku" value="<?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div>
                                        <label>Emplacement</label>
                                        <input name="location" value="<?= htmlspecialchars($product['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div style="display:flex;align-items:end;">
                                        <button class="secondary" type="submit">Mettre à jour</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Derniers mouvements</h2>
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Type</th>
                    <th>Quantité</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$moves): ?>
                    <tr>
                        <td colspan="5" class="muted">Aucun mouvement enregistré.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($moves as $move): ?>
                    <tr>
                        <td><?= htmlspecialchars($move['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="pill <?= htmlspecialchars($move['movement_type'], ENT_QUOTES, 'UTF-8') ?>"><?= $move['movement_type'] === 'in' ? 'Entrée' : 'Sortie' ?></span></td>
                        <td><?= (int)$move['quantity'] ?></td>
                        <td><?= htmlspecialchars($move['note'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($move['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
