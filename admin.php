<?php
session_start();
require_once 'config.php';

$hasImageColumn = false;
$columnCheck = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'image'");
if ($columnCheck) {
    $hasImageColumn = $columnCheck->num_rows > 0;
    $columnCheck->free();
}

function redirectWithFlash(string $message = '', string $error = ''): void
{
    if ($message) {
        $_SESSION['flash_message'] = $message;
    }
    if ($error) {
        $_SESSION['flash_error'] = $error;
    }
    header('Location: admin.php');
    exit;
}

function saveMenuImage(array $file, string &$error = ''): string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Image upload failed. Please try again.';
        return '';
    }
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        $error = 'Invalid image format (jpg, jpeg, png, gif, webp allowed).';
        return '';
    }
    if (getimagesize($file['tmp_name']) === false) {
        $error = 'The uploaded file is not a valid image.';
        return '';
    }
    $dir = 'uploads/menu_items';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $base = preg_replace('/[^A-Za-z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
    $base = $base ?: 'item';
    $newName = time() . '_' . $base . '.' . $ext;
    $target = "$dir/$newName";
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $error = 'Unable to save the uploaded image.';
        return '';
    }
    return $newName;
}

function deleteMenuImage(string $fileName): void
{
    if (!$fileName) {
        return;
    }
    $path = 'uploads/menu_items/' . $fileName;
    if (file_exists($path)) {
        @unlink($path);
    }
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

if (isset($_GET['delete_item']) && ctype_digit($_GET['delete_item'])) {
    $deleteId = (int) $_GET['delete_item'];
    $selectImageColumn = $hasImageColumn ? 'image' : "'' AS image";
    $stmt = $conn->prepare("SELECT $selectImageColumn FROM menu_items WHERE id = ?");
    if (!$stmt) {
        redirectWithFlash('', 'Unable to remove the selected item');
    }
    $stmt->bind_param('i', $deleteId);
    $stmt->execute();
    $stmt->bind_result($imageFile);
    if ($stmt->fetch()) {
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM menu_items WHERE id = ?');
        $stmt->bind_param('i', $deleteId);
        if ($stmt->execute()) {
            deleteMenuImage($imageFile);
            redirectWithFlash('Menu item removed successfully');
        }
        $stmt->close();
    } else {
        $stmt->close();
    }
    redirectWithFlash('', 'Unable to remove the selected item');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_logo'])) {
        $currentLogo = '';
        $stmt = $conn->prepare('SELECT logo FROM settings WHERE id = 1');
        if ($stmt) {
            $stmt->execute();
            $stmt->bind_result($currentLogo);
            $stmt->fetch();
            $stmt->close();
        }
        if ($currentLogo) {
            $file = 'uploads/' . $currentLogo;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $stmt = $conn->prepare('UPDATE settings SET logo = "" WHERE id = 1');
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }
        redirectWithFlash('Logo removed successfully');
    } elseif (isset($_POST['logo_upload'])) {
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Please select a logo image before uploading.';
        } else {
            $targetDir = 'uploads/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileName = basename($_FILES['logo']['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed, true)) {
                $check = getimagesize($_FILES['logo']['tmp_name']);
                if ($check !== false) {
                    $newName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', $fileName);
                    $targetFile = $targetDir . $newName;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
                    $stmt = $conn->prepare('UPDATE settings SET logo = ? WHERE id = 1');
                    $stmt->bind_param('s', $newName);
                    if ($stmt->execute()) {
                        $stmt->close();
                        redirectWithFlash('Logo uploaded successfully');
                    }
                    $stmt->close();
                    $error = 'Error saving logo: ' . $conn->error;
                    } else {
                        $error = 'Error uploading file';
                    }
                } else {
                    $error = 'The uploaded file is not a valid image';
                }
            } else {
                $error = 'Invalid file type. Only JPG, JPEG, PNG, GIF and WEBP allowed';
            }
        }
    } elseif (isset($_POST['category_name'])) {
        $categoryName = trim($_POST['category_name']);
        if ($categoryName !== '') {
            $stmt = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->bind_param('s', $categoryName);
            if ($stmt->execute()) {
                $stmt->close();
                redirectWithFlash('Category added successfully');
            }
            $stmt->close();
            $error = 'Error adding category: ' . $conn->error;
        } else {
            $error = 'Category name cannot be empty';
        }
    } elseif (isset($_POST['update_item'])) {
        $itemId = (int) $_POST['update_item'];
        $itemName = trim($_POST['item_name'] ?? '');
        $itemDesc = trim($_POST['item_desc'] ?? '');
        $itemPrice = trim($_POST['item_price'] ?? '');
        $itemCategory = intval($_POST['item_category'] ?? 0);
        $currentImage = $_POST['current_image'] ?? '';

            if ($itemName !== '' && $itemPrice !== '' && $itemCategory > 0) {
                $price = floatval($itemPrice);
                $imageError = '';
                $newImage = '';
                if ($hasImageColumn && isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newImage = saveMenuImage($_FILES['item_image'], $imageError);
                    if ($imageError) {
                        $error = $imageError;
                    }
                }
                if (!$error) {
                    $imageToSave = $currentImage;
                    if ($newImage) {
                        $imageToSave = $newImage;
                    }
                    if ($hasImageColumn) {
                        $stmt = $conn->prepare('UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ?, image = ? WHERE id = ?');
                    } else {
                        $stmt = $conn->prepare('UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ? WHERE id = ?');
                    }
                    if ($stmt) {
                        if ($hasImageColumn) {
                            $stmt->bind_param('issdsi', $itemCategory, $itemName, $itemDesc, $price, $imageToSave, $itemId);
                        } else {
                            $stmt->bind_param('issdi', $itemCategory, $itemName, $itemDesc, $price, $itemId);
                        }
                        if ($stmt->execute()) {
                            $stmt->close();
                            if ($hasImageColumn && $newImage && $currentImage) {
                                deleteMenuImage($currentImage);
                            }
                            redirectWithFlash('Menu item updated successfully');
                        }
                        $stmt->close();
                        $error = 'Error updating menu item: ' . $conn->error;
                    } else {
                        $error = 'Error preparing update statement: ' . $conn->error;
                    }
                }
            } else {
                $error = 'Please complete all fields for the menu item';
            }
    } elseif (isset($_POST['create_item'])) {
        $itemName = trim($_POST['item_name'] ?? '');
        $itemDesc = trim($_POST['item_desc'] ?? '');
        $itemPrice = trim($_POST['item_price'] ?? '');
        $itemCategory = intval($_POST['item_category'] ?? 0);

        if ($itemName !== '' && $itemPrice !== '' && $itemCategory > 0) {
            $price = floatval($itemPrice);
            $imageError = '';
            $itemImage = '';
            if ($hasImageColumn && isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $itemImage = saveMenuImage($_FILES['item_image'], $imageError);
                if ($imageError) {
                    $error = $imageError;
                }
            }
            if (!$error) {
                if ($hasImageColumn) {
                    $stmt = $conn->prepare('INSERT INTO menu_items (category_id, name, description, price, image) VALUES (?, ?, ?, ?, ?)');
                } else {
                    $stmt = $conn->prepare('INSERT INTO menu_items (category_id, name, description, price) VALUES (?, ?, ?, ?)');
                }
                if ($stmt) {
                    if ($hasImageColumn) {
                        $stmt->bind_param('issds', $itemCategory, $itemName, $itemDesc, $price, $itemImage);
                    } else {
                        $stmt->bind_param('issd', $itemCategory, $itemName, $itemDesc, $price);
                    }
                    if ($stmt->execute()) {
                        $stmt->close();
                        redirectWithFlash('Menu item added successfully');
                    }
                    $stmt->close();
                    $error = 'Error adding menu item: ' . $conn->error;
                } else {
                    $error = 'Error preparing menu item insert statement: ' . $conn->error;
                }
            }
        } else {
            $error = 'Please complete all fields for the menu item';
        }
    }
}

$categories = [];
$result = $conn->query('SELECT id, name FROM categories ORDER BY name');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $result->free();
}

$editingItem = null;
if (isset($_GET['edit_item']) && ctype_digit($_GET['edit_item'])) {
    $editId = (int) $_GET['edit_item'];
    $imageSelect = $hasImageColumn ? ', image' : '';
    $stmt = $conn->prepare('SELECT id, category_id, name, description, price' . $imageSelect . ' FROM menu_items WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $editingItem = $result->fetch_assoc();
        if (!isset($editingItem['image'])) {
            $editingItem['image'] = '';
        }
    }
    if ($result) {
        $result->free();
    }
    $stmt->close();
}

$logoPath = '';
$result = $conn->query('SELECT logo FROM settings WHERE id = 1');
if ($result && $row = $result->fetch_assoc()) {
    $logoPath = $row['logo'];
}
if ($result) {
    $result->free();
}

$itemsByCategory = [];
$imageSelect = $hasImageColumn ? ', menu_items.image' : '';
$query = 'SELECT categories.name AS cat_name, menu_items.id, menu_items.name, menu_items.description, menu_items.price' . $imageSelect . ' '
       . 'FROM menu_items JOIN categories ON menu_items.category_id = categories.id '
       . 'ORDER BY categories.name, menu_items.name';
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cat = $row['cat_name'];
        if (!isset($itemsByCategory[$cat])) {
            $itemsByCategory[$cat] = [];
        }
        $itemsByCategory[$cat][] = $row;
    }
}
if ($result) {
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <h1>Admin Panel</h1>
    <a href="admin.php?action=logout" class="btn btn-secondary" style="float:right; margin-top:-40px;">Logout</a>
  </header>
  <main class="admin-layout">
    <?php if ($message): ?>
      <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="panel">
      <header class="panel-heading">
        <div>
          <p class="panel-label">Brand</p>
          <h2>Site Logo</h2>
        </div>
        <p class="panel-helper">This logo is mirrored on the public menu.</p>
      </header>
      <div class="logo-row">
        <?php if ($logoPath): ?>
          <img class="logo-display" src="uploads/<?php echo htmlspecialchars($logoPath); ?>" alt="Site logo">
        <?php else: ?>
          <span class="placeholder">Logo placeholder</span>
        <?php endif; ?>
      </div>
      <form method="post" enctype="multipart/form-data" class="form-stack">
        <input type="hidden" name="logo_upload" value="1">
        <label for="logo">Upload Logo Image</label>
        <input type="file" name="logo" id="logo" accept="image/*">
        <div class="form-actions">
          <button type="submit" class="btn">Save Logo</button>
          <button type="submit" name="remove_logo" value="1" class="btn btn-danger" <?php echo $logoPath ? '' : 'disabled'; ?>>Remove Logo</button>
        </div>
      </form>
    </section>

    <section class="panel panel-grid">
      <article class="form-card">
        <h3>Add Category</h3>
        <form method="post" class="form-stack">
          <label for="category_name">Category Name</label>
          <input type="text" id="category_name" name="category_name" required>
          <button type="submit" class="btn">Add Category</button>
        </form>
      </article>
      <article class="form-card">
        <h3>Add Menu Item</h3>
        <form method="post" enctype="multipart/form-data" class="form-stack">
          <input type="hidden" name="create_item" value="1">
          <label for="item_category">Category</label>
          <select id="item_category" name="item_category" required>
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <label for="item_name">Item Name</label>
          <input type="text" id="item_name" name="item_name" required>
          <label for="item_desc">Description</label>
          <textarea id="item_desc" name="item_desc" rows="3"></textarea>
          <label for="item_price">Price (e.g., 9.99)</label>
          <input type="number" id="item_price" name="item_price" step="0.01" min="0" required>
          <?php if ($hasImageColumn): ?>
            <label for="item_image">Item Image</label>
            <input type="file" id="item_image" name="item_image" accept="image/*">
          <?php endif; ?>
          <button type="submit" class="btn">Add Menu Item</button>
        </form>
      </article>
    </section>

    <?php if ($editingItem): ?>
      <section class="panel">
        <header class="panel-heading">
          <div>
            <p class="panel-label">Editing</p>
            <h2>Edit "<?php echo htmlspecialchars($editingItem['name']); ?>"</h2>
          </div>
          <a href="admin.php" class="btn btn-secondary small">Cancel</a>
        </header>
        <form method="post" enctype="multipart/form-data" class="form-stack">
          <input type="hidden" name="update_item" value="<?php echo $editingItem['id']; ?>">
          <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($editingItem['image'] ?? ''); ?>">
          <label for="edit_item_category">Category</label>
          <select id="edit_item_category" name="item_category" required>
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] === (int) $editingItem['category_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label for="edit_item_name">Item Name</label>
          <input type="text" id="edit_item_name" name="item_name" value="<?php echo htmlspecialchars($editingItem['name']); ?>" required>
          <label for="edit_item_desc">Description</label>
          <textarea id="edit_item_desc" name="item_desc" rows="3"><?php echo htmlspecialchars($editingItem['description']); ?></textarea>
          <label for="edit_item_price">Price</label>
          <input type="number" id="edit_item_price" name="item_price" step="0.01" min="0" value="<?php echo htmlspecialchars($editingItem['price']); ?>" required>
          <?php if ($hasImageColumn): ?>
            <label for="edit_item_image">Replace Image</label>
            <input type="file" id="edit_item_image" name="item_image" accept="image/*">
          <?php endif; ?>
          <button type="submit" class="btn">Save Changes</button>
        </form>
      </section>
    <?php endif; ?>

    <section class="panel">
      <header class="panel-heading">
        <div>
          <p class="panel-label">Inventory</p>
          <h2>Current Menu</h2>
        </div>
      </header>
      <?php if (empty($itemsByCategory)): ?>
        <p>No menu items available yet.</p>
      <?php else: ?>
        <?php foreach ($itemsByCategory as $catName => $items): ?>
          <div class="category-display">
            <h3><?php echo htmlspecialchars($catName); ?></h3>
            <table>
              <thead>
                <tr>
                  <th>Image</th>
                  <th>Name</th>
                  <th>Description</th>
                  <th>Price</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td>
                      <?php $itemImage = $item['image'] ?? ''; ?>
                      <?php if ($hasImageColumn && $itemImage): ?>
                        <img src="uploads/menu_items/<?php echo htmlspecialchars($itemImage); ?>" alt="" class="image-thumb">
                      <?php elseif ($hasImageColumn): ?>
                        <span class="placeholder small">Upload image</span>
                      <?php else: ?>
                        <span class="placeholder small">Image support disabled</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo '$' . number_format($item['price'], 2); ?></td>
                    <td class="table-actions">
                      <a class="btn-link" href="admin.php?edit_item=<?php echo $item['id']; ?>">Edit</a>
                      <a class="btn-link danger" href="admin.php?delete_item=<?php echo $item['id']; ?>" onclick="return confirm('Remove this item?');">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
