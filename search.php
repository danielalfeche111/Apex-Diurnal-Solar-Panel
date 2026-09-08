<?php
/**
 * search.php - Product & System Catalog Search
 * Conforms to Week 7 Module: Capturing Form Data with $_GET (Slide 3)
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/product_data.php';

// Slide 3: $term = $_GET['term'];
$term = trim($_GET['term'] ?? $_GET['q'] ?? '');

$results = [];
if ($term !== '') {
    foreach ($products as $p) {
        if (stripos($p['title'], $term) !== false || stripos($p['alt'], $term) !== false) {
            $results[] = $p;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results | Apex Diurnal Solar Panels</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .search-container {
            max-width: 900px;
            margin: 3rem auto;
            padding: 2rem;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .search-form {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        .search-input {
            flex: 1;
            padding: 0.85rem 1.25rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        .search-btn {
            background: var(--navy-primary, #1b335f);
            color: #ffffff;
            border: none;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }
        .result-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
        }
        .result-card img {
            max-width: 100%;
            height: 160px;
            object-fit: contain;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body style="background: #f8fafc; font-family: 'Inter', sans-serif;">
    <div class="search-container">
        <h1 style="color: var(--navy-primary, #1b335f); margin-bottom: 1rem;">Catalog Search</h1>
        <p style="color: #64748b; margin-bottom: 1.5rem;">
            Demonstrating <code>$_GET</code> parameter filtering from Week 7 Learning Module.
        </p>

        <!-- Slide 3: <form method="GET" action="search.php"> -->
        <form method="GET" action="search.php" class="search-form">
            <input type="text" name="term" class="search-input" placeholder="Search solar panels, inverters..." value="<?php echo htmlspecialchars($term); ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>

        <?php if ($term !== ''): ?>
            <!-- Slide 3 & 7: echo "You searched: $term"; escaped with htmlspecialchars() -->
            <p style="font-size: 1.1rem; color: #334155; margin-bottom: 1.5rem;">
                You searched for: <strong><?php echo htmlspecialchars($term); ?></strong>
            </p>

            <?php if (!empty($results)): ?>
                <div class="results-grid">
                    <?php foreach ($results as $item): ?>
                        <div class="result-card">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['alt']); ?>">
                            <h3 style="font-size: 1.1rem; color: #1e293b;"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p style="font-weight: 700; color: #0284c7; margin: 0.5rem 0;"><?php echo htmlspecialchars($item['price']); ?></p>
                            <a href="cart/index.php" class="btn btn-yellow" style="display:inline-block; margin-top:0.5rem; text-decoration:none; padding:0.5rem 1rem; border-radius:6px; font-weight:600; background:#fee000; color:#1b335f;">View in Cart</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding: 2rem; background: #f1f5f9; border-radius: 8px; text-align: center; color: #64748b;">
                    No products matched "<strong><?php echo htmlspecialchars($term); ?></strong>". Try searching for "solar", "array", or "inverter".
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
            <a href="index.php" style="color: #1b335f; font-weight: 600; text-decoration: none;">&larr; Back to Homepage</a>
        </div>
    </div>
</body>
</html>
