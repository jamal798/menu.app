<?php
require_once 'config.php';

// Helpers
function resolveMenuImage(string $itemName): string
{
    $imageDir = __DIR__ . '/uploads/menu_items';
    $relativeDir = 'uploads/menu_items';
    $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($itemName));
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    foreach ($extensions as $ext) {
        $candidate = "$imageDir/$slug.$ext";
        if (file_exists($candidate)) {
            return "$relativeDir/$slug.$ext";
        }
    }
    return '';
}

function slugify(string $text): string
{
    $slug = mb_strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'category';
}

// Fetch logo from settings
$logoPath = '';
$result = $conn->query('SELECT logo FROM settings WHERE id = 1');
if ($result && $row = $result->fetch_assoc()) {
    $logoPath = $row['logo'];
}
if ($result) $result->free();

// Determine whether the `image` column exists before selecting it
$hasImageColumn = false;
$columnCheck = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'image'");
if ($columnCheck) {
    $hasImageColumn = $columnCheck->num_rows > 0;
    $columnCheck->free();
}

// Fetch menu items grouped by category with optional search
$itemsByCategory = [];
$searchTerm = trim($_GET['q'] ?? '');
$likeTerm = '%' . $searchTerm . '%';
$imageSelect = $hasImageColumn ? ', menu_items.image' : '';
$query = 'SELECT categories.name AS cat_name, menu_items.name, menu_items.description, menu_items.price' . $imageSelect . ' '
       . 'FROM menu_items JOIN categories ON menu_items.category_id = categories.id '
       . 'WHERE (? = "" OR menu_items.name LIKE ? OR menu_items.description LIKE ?) '
       . 'ORDER BY categories.name, menu_items.name';
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('sss', $searchTerm, $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cat = $row['cat_name'];
            if (!isset($itemsByCategory[$cat])) {
                $itemsByCategory[$cat] = [];
            }
            $imageValue = $hasImageColumn ? ($row['image'] ?? '') : '';
            $row['image_path'] = $imageValue
                ? 'uploads/menu_items/' . $imageValue
                : resolveMenuImage($row['name']);
            $itemsByCategory[$cat][] = $row;
        }
    }
    if ($result) {
        $result->free();
    }
    $stmt->close();
} else {
    trigger_error('Menu query failed: ' . $conn->error, E_USER_WARNING);
    $result = $conn->query('SELECT categories.name AS cat_name, menu_items.name, menu_items.description, menu_items.price '
           . 'FROM menu_items JOIN categories ON menu_items.category_id = categories.id '
           . 'ORDER BY categories.name, menu_items.name');
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cat = $row['cat_name'];
            if (!isset($itemsByCategory[$cat])) {
                $itemsByCategory[$cat] = [];
            }
            $row['image_path'] = resolveMenuImage($row['name']);
            $itemsByCategory[$cat][] = $row;
        }
    }
    if ($result) {
        $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menu</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="menu-page">
    <section class="logo-showcase">
      <?php if ($logoPath): ?>
        <div class="logo-frame">
          <img src="uploads/<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="logo-display">
        </div>
        <p class="logo-caption">Our brand, curated by you</p>
      <?php else: ?>
        <p class="logo-caption">Upload your logo from the admin panel to personalize this menu.</p>
      <?php endif; ?>
    </section>

    <section class="menu-controls">
      <form class="search-form" method="get">
        <input
          type="search"
          name="q"
          class="search-input"
          placeholder="Search by dish name or description"
          value="<?php echo htmlspecialchars($searchTerm); ?>"
          aria-label="Search menu items"
        >
        <button type="submit" class="btn btn-secondary search-button">Search</button>
      </form>
      <?php if (!empty($itemsByCategory)): ?>
        <div class="category-line" role="navigation">
          <?php foreach ($itemsByCategory as $catName => $items): ?>
            <a class="category-link" href="#category-<?php echo htmlspecialchars(slugify($catName)); ?>">
              <?php echo htmlspecialchars($catName); ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if (empty($itemsByCategory)): ?>
      <p class="empty-state">
        <?php if ($searchTerm): ?>
          No menu items match "<?php echo htmlspecialchars($searchTerm); ?>". Try a different keyword.
        <?php else: ?>
          The menu is currently empty. Please check back later.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <?php foreach ($itemsByCategory as $catName => $items): ?>
        <section class="category-section" id="category-<?php echo htmlspecialchars(slugify($catName)); ?>">
          <div class="category-heading">
            <div>
              <p class="category-label">Category</p>
              <h2><?php echo htmlspecialchars($catName); ?></h2>
            </div>
            <span class="category-count"><?php echo count($items); ?> items</span>
          </div>
          <div class="menu-grid">
            <?php foreach ($items as $item): ?>
              <article class="menu-card">
                <div class="menu-card-media">
                  <?php if ($item['image_path']): ?>
                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                  <?php else: ?>
                    <div class="menu-card-placeholder">
                      <span>Image coming soon</span>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="menu-card-body">
                  <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                  <?php if (!empty($item['description'])): ?>
                    <p class="menu-description"><?php echo htmlspecialchars($item['description']); ?></p>
                  <?php endif; ?>
                </div>
                <div class="menu-card-footer">
                  <span class="price-tag">
                    <?php echo '$' . number_format($item['price'], 2); ?>
                  </span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</body>
</html>
